@extends('errors._ecosystem-layout', ['code' => 403, 'state' => 'stop'])

@section('title', "You don't have access to this")
@section('message', 'Ask the owner of this business if you think you should have access.')

@section('action')
    <a class="button solid" href="/">Go home</a>
@endsection
