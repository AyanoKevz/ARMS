{{--
    One AF-PCN-A1 / AF-PCR-A2 table.

    Expects:
      $columns  array of [label, width class suffix, (optional) cell class]
                — widths are percentages from af-pcn-a1.css (.af-w-6 … .af-w-42);
                a "\n" in a label breaks the line, as on the printed form
      $rows     array of rows, each an array of display strings
    Optional:
      $group    [label, firstColumnIndex, span] — a header cell over several
                columns, e.g. "Inclusive Dates" over From / To
      $tall     true for page 1's taller rows
      $total    [columnIndex, value] — a "Total ____" line under the table,
                its value under that column (the AF-PCR-A2's hours)
--}}
@php
    $group = $group ?? null;
    $total = $total ?? null;
    $head  = fn (string $label) => nl2br(e($label), false);
@endphp
<table class="af-grid {{ ($tall ?? false) ? 'af-grid-tall' : '' }} {{ $total ? 'af-grid-with-total' : '' }}">
    <thead>
        @if($group)
            <tr>
                @foreach($columns as $i => $col)
                    @if($i === $group[1])
                        <th colspan="{{ $group[2] }}">{!! $head($group[0]) !!}</th>
                    @elseif($i < $group[1] || $i >= $group[1] + $group[2])
                        <th rowspan="2" class="af-w-{{ $col[1] }}">{!! $head($col[0]) !!}</th>
                    @endif
                @endforeach
            </tr>
            <tr>
                @for($i = $group[1]; $i < $group[1] + $group[2]; $i++)
                    <th class="af-w-{{ $columns[$i][1] }}">{!! $head($columns[$i][0]) !!}</th>
                @endfor
            </tr>
        @else
            <tr>
                @foreach($columns as $col)
                    <th class="af-w-{{ $col[1] }}">{!! $head($col[0]) !!}</th>
                @endforeach
            </tr>
        @endif
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                @foreach($row as $i => $cell)
                    <td class="af-value {{ $columns[$i][2] ?? '' }}">{{ $cell }}</td>
                @endforeach
            </tr>
        @endforeach
        @if($total)
            <tr class="af-grid-total">
                <td class="af-total-label" colspan="{{ $total[0] }}">Total</td>
                <td class="af-total-value af-value af-center">{{ $total[1] }}</td>
                @if(count($columns) - $total[0] - 1 > 0)
                    <td colspan="{{ count($columns) - $total[0] - 1 }}"></td>
                @endif
            </tr>
        @endif
    </tbody>
</table>
