@extends('errors._ecosystem-layout', ['code' => 419, 'state' => 'hold'])

@section('title', 'Your session timed out')
@section('message', 'This form expired while it was open. Go back and send it again.')

@section('action')
    <a class="button solid" href="{{ url()->previous() === url()->current() ? '/' : url()->previous() }}">Go back</a>
@endsection
