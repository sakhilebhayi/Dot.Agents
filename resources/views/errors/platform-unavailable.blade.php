{{-- R-61: InfoDot has switched Dot.Agents off (maintenance or unavailable). Rendered with HTTP 503. It says so plainly and
     reads nothing of the business: agents, schedules, runs and every record are kept exactly as they are. It is not a
     purchase or permission problem, so it offers no upgrade action. --}}
@extends('errors._ecosystem-layout', ['code' => 503, 'state' => 'hold'])

@section('title', 'Dot.Agents is not available right now')
@section('message', ($message ?? 'Dot.Agents is temporarily unavailable.').' Everything recorded here is kept as it is.')

@section('action')
    <a class="button solid" href="/">Try again</a>
@endsection
