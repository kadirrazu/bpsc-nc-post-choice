<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChoiceEvent extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['start_at'=>'datetime','end_at'=>'datetime','multiple_posts'=>'boolean']; }
    public function posts() { return $this->hasMany(EventPost::class); }
    public function choices() { return $this->hasMany(ChoiceOption::class)->orderBy('sort_order')->orderBy('id'); }
    public function candidates() { return $this->hasMany(EventCandidate::class); }
    public function scopeAvailable($query) { return $query->where('status','PUBLISHED')->where('start_at','<=',now())->where('end_at','>=',now()); }
    public static function unitOptions(): array {
        $units=[];
        for ($i=1;$i<=20;$i++) { $label=sprintf('Unit %02d',$i); $units[$label]=$label; }
        $units['Non Cadre (Exam)']='Non Cadre (Exam)';
        return $units;
    }
    public static function statusOptions(): array { return ['DRAFT'=>'Draft','PUBLISHED'=>'Published','CLOSED'=>'Closed','ARCHIVED'=>'Archived','CANCELLED'=>'Cancelled']; }
    public function getLifecycleAttribute(): string {
        if ($this->status==='CLOSED') return 'CLOSED';
        if ($this->status==='CANCELLED') return 'CANCELLED';
        if ($this->status==='ARCHIVED' || $this->end_at->lt(now())) return 'ARCHIVED';
        return $this->status==='PUBLISHED' && $this->start_at->gt(now()) ? 'SCHEDULED' : $this->status;
    }
}
