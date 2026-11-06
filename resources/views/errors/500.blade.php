@extends('errors._ecosystem-layout', ['code' => 500, 'state' => 'stop'])

@section('title', 'Something went wrong on our end')
@section('message', 'An unexpected error stopped this page. Try again in a moment.')

@section('action')
    <a class="button solid" href="/">Go home</a>
@endsection
