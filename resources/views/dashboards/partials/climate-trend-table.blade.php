@if($records->isEmpty())
    <div class="empty-soft"><strong>No recent records</strong><div class="small mt-1" style="color: rgba(255,255,255,.5);">Climate records will appear here once logged.</div></div>
@else
    <div class="table-responsive">
        <table class="table table-sm fc-modal-table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="{{ $highlight === 'temperature' ? 'fc-modal-table-highlight' : '' }}">Temp (&deg;C)</th>
                    <th class="{{ $highlight === 'rainfall' ? 'fc-modal-table-highlight' : '' }}">Rainfall (mm)</th>
                    <th class="{{ $highlight === 'humidity' ? 'fc-modal-table-highlight' : '' }}">Humidity (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr>
                        <td>{{ $record->record_date?->format('M d, Y') }}</td>
                        <td class="{{ $highlight === 'temperature' ? 'fc-modal-table-highlight' : '' }}">{{ $record->temperature !== null ? number_format($record->temperature, 1) : '--' }}</td>
                        <td class="{{ $highlight === 'rainfall' ? 'fc-modal-table-highlight' : '' }}">{{ $record->rainfall !== null ? number_format($record->rainfall, 1) : '--' }}</td>
                        <td class="{{ $highlight === 'humidity' ? 'fc-modal-table-highlight' : '' }}">{{ $record->humidity !== null ? number_format($record->humidity, 1) : '--' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
