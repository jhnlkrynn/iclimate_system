<x-app-layout>
<div class="container py-4">
<h1>{{ $period->label }}</h1>
<p>{{ $assignment->association->name }} · Enter observed association totals only.</p>
@if ($errors->any())
<div class="alert alert-danger" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@include('association-chairman._reviews', ['reviews' => $report->exists ? $report->reviews()->with('reviewer')->latest()->get() : collect()])
<form method="POST" enctype="multipart/form-data" action="{{ route('association-chairman.reports.store', $period) }}">
@csrf
@foreach ([['planting_date','Planting date','date'],['harvest_date','Harvest date','date'],['area_planted_hectares','Area planted (hectares)','number'],['area_harvested_hectares','Area harvested (hectares)','number'],['production_tons','Production (metric tons)','number'],['participating_farmers','Participating farmers','number']] as [$name,$label,$type])
<label class="mt-2" for="report-{{ $name }}">{{ $label }}</label>
<input id="report-{{ $name }}" class="form-control" type="{{ $type }}" @if($type === 'number') step="{{ $name === 'participating_farmers' ? '1' : '0.01' }}" min="0" @endif name="{{ $name }}" value="{{ old($name, $type === 'date' ? $report->$name?->format('Y-m-d') : $report->$name) }}">
@endforeach
<label class="mt-2" for="report-variety">Rice variety</label><input id="report-variety" class="form-control" name="rice_variety" value="{{ old('rice_variety', $report->rice_variety) }}">
<label class="mt-2" for="report-irrigation">Irrigation</label>
<select id="report-irrigation" class="form-control" name="irrigation_type">
<option value="">Unknown</option>
@foreach (['Rainfed', 'Irrigated'] as $type)<option value="{{ $type }}" @selected(old('irrigation_type', $report->irrigation_type) === $type)>{{ $type }}</option>@endforeach
</select>
<label class="mt-2" for="report-losses">Crop losses</label><textarea id="report-losses" class="form-control" name="crop_losses">{{ old('crop_losses', $report->crop_losses) }}</textarea>
<label class="mt-2" for="report-remarks">Remarks</label><textarea id="report-remarks" class="form-control" name="remarks">{{ old('remarks', $report->remarks) }}</textarea>
<label class="mt-2" for="report-document">Supporting document</label><input id="report-document" class="form-control" type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png">
<button class="btn btn-secondary mt-3" name="action" value="draft">Save draft</button>
<button class="btn btn-primary mt-3" name="action" value="submit">Submit formally</button>
</form></div>
</x-app-layout>
