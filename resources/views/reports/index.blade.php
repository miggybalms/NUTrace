@php
    // Two roles read the same reports through their own shell: the Asset
    // Management Office's sidebar, or the Department Head's. Everything below
    // the header is the same page for both.
    $layout = $layout ?? 'layouts.admin_sidebar';
@endphp

@extends($layout)

@section('title', 'Reports')

@section('content')

    @if($isAdmin)
        @include('admin.partials.header', [
            'adminHeaderPage'  => 'reports',
            'adminHeaderTitle' => 'Reports',
            'adminHeaderIcon'  => 'ri-file-chart-line',
            'adminHeaderBadge' => 'Asset Management Office',
        ])
    @else
        @include('layouts.department_head_header', [
            'title'      => 'Reports',
            'showSearch' => false,
        ])
    @endif

    <div class="p-4 sm:p-8">
        @include('reports.partials.body')
    </div>

@endsection
