@extends('layouts.user_sidebar')

@section('title', ($asset->Asset_name ?? 'Asset') . ' — My Assets')

@section('content')

    @include('layouts.user_header', [
        'title'      => 'Asset Details',
        'showSearch' => false,
    ])

    @include('partials.asset_details', [
        'asset'   => $asset,
        'details' => $details,
        'backUrl' => $backUrl,
    ])

@endsection
