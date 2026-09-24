@php
    /**
     * Trim trailing zeros so a whole number prints as "72" and not "72.00".
     */
    $fmt = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    };
@endphp

<table class="header">
    <tr>
        <td class="logo-cell">
            @if (! empty($school['logo']))
                <img class="logo" src="{{ $school['logo'] }}" alt="logo">
            @endif
        </td>
        <td class="school">
            <div class="name">{{ $school['name'] }}</div>
            @if (! empty($school['motto']))
                <div class="motto">{{ $school['motto'] }}</div>
            @endif
            @if (! empty($school['address']))
                <div class="address">{{ $school['address'] }}</div>
            @endif
        </td>
        <td class="logo-cell"></td>
    </tr>
</table>

<div class="sheet-title">
    RESULT SHEET FOR {{ strtoupper($result->academic_session_name) }} ACADEMIC SESSION
</div>
@if ($result->school_section)
    <div class="section-title">{{ $result->school_section }}</div>
@endif

<table class="layout">
    <tr>
        <td class="left">
            <div class="block-title">A. Performance in Subjects</div>

            @foreach ($result->terms as $term)
                <div class="term-block">
                    @if (count($result->terms) > 1)
                        <div class="term-caption">{{ $term['name'] }}</div>
                    @endif

                    @php
                        $graded = collect($result->subjects)->filter(
                            fn ($row) => ($row->terms[$term['id']] ?? null) !== null
                        );
                    @endphp

                    <table class="marks">
                        <thead>
                            <tr>
                                <th rowspan="2">SUBJECTS</th>
                                <th colspan="3">MARK OBTAINABLE %</th>
                                <th rowspan="2">GRADE</th>
                                <th rowspan="2">REMARKS</th>
                            </tr>
                            <tr>
                                <th>CA</th>
                                <th>EXAM</th>
                                <th>TOTAL SCORE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($graded as $row)
                                @php($cell = $row->terms[$term['id']])
                                <tr>
                                    <td class="subject">{{ strtoupper($row->subject_name) }}</td>
                                    <td class="num">{{ $fmt($cell['ca'] ?? null) }}</td>
                                    <td class="num">{{ $fmt($cell['exam'] ?? null) }}</td>
                                    <td class="num">{{ $fmt($cell['total'] ?? null) }}</td>
                                    <td class="num">{{ $cell['grade'] ?? '-' }}</td>
                                    <td class="subject">{{ $cell['remark'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">No results recorded for this term.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach

            <table class="totals">
                <tr>
                    <td class="label">TOTAL CUMULATIVE</td>
                    <td>{{ $fmt($result->total_obtainable) }}</td>
                    <td class="label">SCORE OBTAINED</td>
                    <td>{{ $fmt($result->total_obtained) }}</td>
                    <td class="label">PERCENTAGE AVERAGE</td>
                    <td>{{ $fmt($result->average) }}%</td>
                </tr>
            </table>
        </td>

        <td class="right">
            <div class="block-title">Student Details</div>

            <table class="identity">
                <tr>
                    <td class="label">NAME OF STUDENT</td>
                    <td class="value">{{ strtoupper($result->student_name) }}</td>
                </tr>
                <tr>
                    <td class="label">ADMISSION NO.</td>
                    <td class="value">{{ $result->admission_number }}</td>
                </tr>
                <tr>
                    <td class="label">CLASS</td>
                    <td class="value">
                        {{ strtoupper($result->class_level_name) }}{{ $result->class_arm_name ? ' '.strtoupper($result->class_arm_name) : '' }}
                    </td>
                </tr>
                <tr>
                    <td class="label">AVERAGE</td>
                    <td class="value">{{ $fmt($result->average) }}%</td>
                </tr>
                <tr>
                    <td class="label">EVALUATION</td>
                    <td class="value">{{ $result->evaluation ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">TERM</td>
                    <td class="value">{{ implode(', ', array_column($result->terms, 'name')) }}</td>
                </tr>
                <tr>
                    <td class="label">NUMBER IN CLASS</td>
                    <td class="value">{{ $result->number_in_class }}</td>
                </tr>
                <tr>
                    <td class="label">POSITION</td>
                    <td class="value">{{ $result->position }}</td>
                </tr>
                <tr>
                    <td class="label">SEX</td>
                    <td class="value">{{ $result->gender ? ucfirst($result->gender) : '-' }}</td>
                </tr>
            </table>

            <table class="key-grade">
                <thead>
                    <tr>
                        <th colspan="2">KEY GRADE</th>
                    </tr>
                    <tr>
                        <th>GRADE</th>
                        <th>SCORE RANGE</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($result->grades as $grade)
                        <tr>
                            <td class="label">{{ $grade['label'] ?? '-' }}</td>
                            <td>{{ $fmt($grade['min_score'] ?? null) }} &ndash; {{ $fmt($grade['max_score'] ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">No grading scale configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>