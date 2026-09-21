@extends('layouts.department_head_sidebar')

@section('title', ($asset->Asset_name ?? 'Asset') . ' — My Assets')

@section('content')

    @include('layouts.department_head_header', [
        'title'      => 'Asset Details',
        'showSearch' => false,
    ])

    @include('partials.asset_details', [
        'asset'        => $asset,
        'details'      => $details,
        'repairAction' => $repairAction,
        'backUrl'      => $backUrl,
    ])

@endsection
