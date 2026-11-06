@extends('errors._ecosystem-layout', ['code' => 503, 'state' => 'hold'])

@section('title', "We'll be right back")
@section('message', 'Dot.Agents is down for maintenance. Check back shortly.')

@section('action')
    <a class="button solid" href="/">Try again</a>
@endsection
