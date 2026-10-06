<h2>Candidate & Submission Information</h2>
<table class="receipt-details"><tbody>
@foreach(['User ID'=>'user','Registration'=>'reg','Name'=>'name',"Father's Name"=>'fname','Birth Date'=>'dob','Submission Token'=>'token','Submission Timestamp'=>'submitted_at','Submitted From (IP Address)'=>'submitted_from'] as $label=>$key)
<tr><th>{{ $label }}</th><td>{{ $details[$key] ?: '—' }}</td></tr>@endforeach
</tbody></table>
<h2>Final Submitted Choices</h2>
<table class="receipt-choices"><thead><tr><th class="receipt-number receipt-choice-heading" style="width:18%;text-align:center;vertical-align:middle;white-space:nowrap">Preference</th><th>Choice Title</th></tr></thead><tbody>
@foreach($items as $item)<tr><td class="receipt-number" style="text-align:center;vertical-align:middle">{{ $item->preference_order }}</td><td><span data-choice-title class="{{ preg_match('/[\x{0980}-\x{09FF}]/u',$item->title) ? 'choice-title-bn' : '' }}">{{ $item->title }}</span><br><span class="receipt-choice-code" style="font-size:9pt">Choice Code: {{ $item->code }}</span></td></tr>@endforeach
</tbody></table>
