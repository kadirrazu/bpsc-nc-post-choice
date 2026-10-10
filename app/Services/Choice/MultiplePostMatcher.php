<?php
namespace App\Services\Choice;
use App\Models\ChoiceEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class MultiplePostMatcher {
    public const EXACT=['name','b_date','ssc_roll','ssc_year'];
    public const IDENTITY=['name','fname','mname','b_date','ssc_roll','ssc_year','hsc_roll','hsc_year','nid'];
    public function catalog(ChoiceEvent $event): array {
        return $event->candidates()->with(['applications'=>fn($q)=>$q->orderBy('id')->select('id','event_candidate_id','event_post_id','source_identity')])->orderBy('id')->get()->map(function ($candidate) {
            $data=$candidate->only(array_merge(['id'],self::IDENTITY));
            $data['b_date']=$candidate->b_date->format('Y-m-d');
            $data['post_ids']=$candidate->applications->pluck('event_post_id')->all();
            $data['aliases']=$candidate->applications->filter(fn($app)=>$app->source_identity)->map(fn($app)=>json_decode($app->source_identity,true))->filter(fn($identity)=>is_array($identity))->values()->all();
            return $data;
        })->all();
    }
    public function revision(ChoiceEvent $event,array $catalog): string {
        // Invalidate pending previews produced by the older, permissive matcher.
        return hash('sha256',json_encode(['review-rules-20261010-2',$catalog,
            DB::table('candidate_applications')->whereIn('event_candidate_id',$event->candidates()->select('id'))->orderBy('id')->get(['id','event_candidate_id','event_post_id','user','reg','source_identity']),
            DB::table('candidate_imports')->whereIn('event_post_id',$event->posts()->select('id'))->orderBy('id')->pluck('id'),
            DB::table('choice_audits')->where('choice_event_id',$event->id)->where('action','CANDIDATE_DATASET_RESET')->max('id')],JSON_UNESCAPED_UNICODE));
    }
    private function key(array $row): string { return json_encode(array_map(fn($field)=>(string)($row[$field]??''),self::EXACT),JSON_UNESCAPED_UNICODE); }
    public function plan(array $rows,array $catalog,array $rowNumbers=[]): array {
        $exact=[]; $birth=[]; $ssc=[]; $text=[]; $support=[]; $seen=[]; $plan=[];
        $versions=[];
        foreach ($catalog as $person) foreach (array_merge([$person],$person['aliases']??[]) as $identityIndex=>$identity) {
            unset($identity['aliases']); $identity['candidate_id']=$person['id']; $identity['identity_index']=$identityIndex; $identity['id']=count($versions)+1; $versions[]=$identity;
        }
        foreach ($versions as $candidate) {
            $id=$candidate['id']; $exact[$this->key($candidate)][]=$id;
            $birth[$candidate['b_date']][]=$id;
            foreach (['name','fname','mname'] as $field) foreach ($this->reviewKeys($candidate[$field]??'') as $key) $text[$field][$key][]=$id;
            if (!empty($candidate['nid'])) $support['nid:'.$candidate['nid']][]=$id;
            if (!empty($candidate['hsc_roll']) && !empty($candidate['hsc_year'])) $support['hsc:'.$candidate['hsc_roll'].'|'.$candidate['hsc_year']][]=$id;
            if (!empty($candidate['ssc_roll']) && !empty($candidate['ssc_year'])) $ssc[$candidate['ssc_roll'].'|'.$candidate['ssc_year']][]=$id;
        }
        $people=array_column($versions,null,'id');
        foreach ($rows as $i=>$row) {
            foreach (self::EXACT as $field) if (trim((string)($row[$field]??''))==='') throw ValidationException::withMessages(['file'=>'Row '.($rowNumbers[$i]??$i+2).': '.$field.' is required for multiple-post imports.']);
            $key=$this->key($row);
            if (isset($seen[$key])) throw ValidationException::withMessages(['file'=>'Rows '.$seen[$key].' and '.($rowNumbers[$i]??$i+2).': the same exact identity appears twice for this applied post.']);
            $seen[$key]=$rowNumbers[$i]??$i+2;
            $exactVersions=$exact[$key]??[]; $matches=[]; $comparisons=[];
            foreach ($exactVersions as $version) { $id=$people[$version]['candidate_id']; $matches[]=$id; $comparisons[$id]=$people[$version]['identity_index']; }
            $matches=array_values(array_unique($matches));
            $kind=count($matches)===1 ? 'exact' : (count($matches)>1 ? 'review':'new');
            $reasons=[];
            if (!$matches) {
                $pool=array_unique(array_merge($birth[$row['b_date']]??[],$ssc[$row['ssc_roll'].'|'.$row['ssc_year']]??[]));
                foreach (['name','fname','mname'] as $field) foreach ($this->reviewKeys($row[$field]??'') as $key) $pool=array_merge($pool,$text[$field][$key]??[]);
                if (!empty($row['nid'])) $pool=array_merge($pool,$support['nid:'.$row['nid']]??[]);
                if (!empty($row['hsc_roll']) && !empty($row['hsc_year'])) $pool=array_merge($pool,$support['hsc:'.$row['hsc_roll'].'|'.$row['hsc_year']]??[]);
                foreach (array_unique($pool) as $id) {
                    $c=$people[$id]; $candidateId=$c['candidate_id']; $name=$this->similar($row['name'],$c['name']);
                    $father=$this->similar($row['fname']??null,$c['fname']??null);
                    $mother=$this->similar($row['mname']??null,$c['mname']??null);
                    $nid=!empty($row['nid']) && (string)$row['nid']===(string)($c['nid']??'');
                    $hsc=!empty($row['hsc_roll']) && !empty($row['hsc_year']) && (string)$row['hsc_roll']===(string)($c['hsc_roll']??'') && (string)$row['hsc_year']===(string)($c['hsc_year']??'');
                    $dob=(string)$row['b_date']===(string)$c['b_date'];
                    $sscMatch=(string)$row['ssc_roll']===(string)($c['ssc_roll']??'') && (string)$row['ssc_year']===(string)($c['ssc_year']??'');
                    // A name resemblance needs independent evidence; shared surnames are insufficient.
                    if (($name && ($dob || $sscMatch || $father || $mother)) || ($father && $mother && ($dob || $sscMatch)) || $nid || $hsc) {
                        $matches[]=$candidateId; $comparisons[$candidateId]=$c['identity_index'];
                        if (count($comparisons)>50) throw ValidationException::withMessages(['file'=>'Row '.($rowNumbers[$i]??$i+2).': more than 50 possible identity matches. Correct the mandatory identity fields or add reliable supporting information before importing.']);
                        $reasons[$candidateId]=implode(', ',array_filter([$name ? 'Name similarity':null,$father ? 'Father name similarity':null,$mother ? 'Mother name similarity':null,$dob ? 'DOB exact match':null,$sscMatch ? 'SSC roll/year exact match':null,$nid ? 'NID exact match':null,$hsc ? 'HSC roll/year exact match':null]));
                    }
                }
                $matches=array_values(array_unique($matches));
                if ($matches) $kind='review';
            }
            $plan[]=['line'=>$rowNumbers[$i]??$i+2,'row'=>$row,'kind'=>$kind,'matches'=>$matches,'reasons'=>$reasons,'comparisons'=>$comparisons,'decision'=>$kind==='exact' ? 'link:'.$matches[0] : ($kind==='new' ? 'new':null)];
        }
        return $plan;
    }
    private function reviewKeys(string $value): array {
        $value=trim(preg_replace('/[^\p{L}\p{M}\p{N}]+/u',' ',mb_strtolower($value)));
        if ($value==='') return [];
        // Indexed name-token anchors also find partial identities with differing DOB/SSC.
        $keys=['full:'.$value];
        foreach (explode(' ',$value) as $word) if (mb_strlen($word)>=4 && !in_array($word,['mohammad','mohammed','muhammad','begum','islam'],true)) $keys[]='prefix:'.mb_substr($word,0,3);
        return array_unique($keys);
    }
    /** Review suggestions only. Exact matching never uses this normalization. */
    public function similar(?string $a,?string $b): bool {
        $normalize=fn($v)=>trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{M}\p{N}\s]/u',' ',mb_strtolower((string)$v))));
        $honorifics=['md','mst','mosammat','most','mohammad','mohammed','muhammad','মো','মোঃ','মোহাম্মদ','মুহাম্মদ','মোসা','মোসাঃ','মোছা','মোছাঃ','মোছাম্মৎ','মোসাম্মৎ'];
        $tokens=fn($value)=>array_values(array_diff(array_unique(explode(' ',$normalize($value))),array_merge([''],$honorifics)));
        $left=$tokens($a); $right=$tokens($b);
        if (!$left || !$right) return false;
        $common=['hossain','hosain','hussain','hossen','islam','begum','ali','uddin','হোসেন','হোসাইন','ইসলাম','বেগম','আলী','উদ্দিন'];
        $matched=0; $meaningful=false; $used=[];
        // Exact tokens first so fuzzy matches cannot consume another token's exact match.
        foreach ([false,true] as $fuzzy) foreach ($left as $i=>$word) {
            if (isset($used['left:'.$i])) continue;
            foreach ($right as $j=>$other) {
                if (isset($used['right:'.$j])) continue;
                if (!$fuzzy ? $word===$other : $this->minorSpellingDifference($word,$other)) {
                    $used['left:'.$i]=true; $used['right:'.$j]=true; $matched++;
                    if (!in_array($word,$common,true) && !in_array($other,$common,true)) $meaningful=true;
                    break;
                }
            }
        }
        if (!$meaningful) return false;
        // Shortened names need at least two complete tokens, never a surname substring.
        return $matched/max(count($left),count($right))>=0.75
            || ($matched>=2 && $matched===min(count($left),count($right)));
    }
    private function minorSpellingDifference(string $a,string $b): bool {
        // Compare individual words: a shared long surname cannot hide a different given name.
        $x=mb_str_split($a); $y=mb_str_split($b); $length=max(count($x),count($y));
        if (min(count($x),count($y))<4 || abs(count($x)-count($y))>$length*0.2) return false;
        $previous=range(0,count($y));
        foreach ($x as $i=>$char) {
            $current=[$i+1];
            foreach ($y as $j=>$other) $current[$j+1]=min($current[$j]+1,$previous[$j+1]+1,$previous[$j]+($char===$other ? 0:1));
            $previous=$current;
        }
        return end($previous)/$length<=0.2;
    }
}
