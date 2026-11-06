@extends('errors._ecosystem-layout', ['code' => 429, 'state' => 'hold'])

@section('title', 'Slow down a little')
@section('message', 'Too many requests in a short time. Wait a minute and try again.')

@section('action')
    <a class="button solid" href="/">Go home</a>
@endsection
