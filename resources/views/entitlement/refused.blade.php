{{-- R-50: the company does not hold Dot.Agents. Reuses the platform's error page (the run line, stopped or held) with
     ONE primary action. Rendered by App\Support\EntitlementRefusal with HTTP 403. It shows nothing from the business. --}}
@extends('errors._ecosystem-layout', ['code' => 403, 'state' => $state])

@section('title', $title)
@section('message', $body)

@section('action')
    <a class="button solid" href="{{ $platformsUrl }}" data-primary-action>Open Platforms in InfoDot</a>

    @auth
        @if ($otherCompanies->isNotEmpty())
            <section class="gate-others" aria-labelledby="gate-others">
                <h2 id="gate-others">Your other {{ $otherCompanies->count() === 1 ? 'business' : 'businesses' }} here</h2>
                <ul>
                    @foreach ($otherCompanies as $other)
                        <li>
                            <span>{{ $other->name }}</span>
                            <form method="POST" action="{{ route('organization.switch') }}">
                                @csrf
                                <input type="hidden" name="organization_id" value="{{ $other->id }}">
                                <button type="submit" class="button" aria-label="Switch to {{ $other->name }}">Switch</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endauth
@endsection
