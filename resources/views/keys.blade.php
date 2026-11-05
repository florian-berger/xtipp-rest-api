@extends('layouts.app')
@section('title', trans('rest-api::ui.keys.sidename'))
@section('content')
    {{-- The messages of the session (success/info/danger) are shown by the layout (layouts.messages) --}}
    @include('layouts.form-errors', ['fields' => ['expires_at' => 'expires_at']])

    <div class="section-title">
        {{ trans('rest-api::ui.keys.title') }}
    </div>

    <p class="mt-2">{!! trans('rest-api::ui.keys.intro') !!}</p>
    <p class="mt-2 text-sm text-gray-500">{{ trans('rest-api::ui.keys.warning') }}</p>
    <p class="mt-2">
        <a href="{{ url('/api/docs') }}" class="link-normal">{{ trans('rest-api::ui.keys.docs') }}</a>
    </p>

    @if ($newKey !== null)
        <div class="mt-5 p-3 bg-slate-200">
            <div class="font-bold">{{ trans('rest-api::ui.keys.new-title') }}</div>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <input id="rest-api-new-key" type="text" class="input-normal-gray w-full md:w-3/4 font-mono" value="{{ $newKey }}" readonly autocomplete="off" spellcheck="false" onfocus="this.select()">
                <button type="button" class="btn-small" onclick="var i = document.getElementById('rest-api-new-key'); i.select(); if (navigator.clipboard) { navigator.clipboard.writeText(i.value); }">{{ trans('rest-api::ui.keys.btn.copy') }}</button>
            </div>
            <p class="mt-2 text-sm">{{ trans('rest-api::ui.keys.new-hint') }}</p>
        </div>
        {{-- The page is the answer of a POST request: a reload must show the key page (without the key) and must not send the form again --}}
        <script>
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', @json(route('plugin-rest-api.keys')));
            }
        </script>
    @endif

    @if ($apiKey === null)
        <p class="mt-5">{{ trans('rest-api::ui.keys.none') }}</p>

        <form method="POST" action="{{ route('plugin-rest-api.keys.create') }}" class="mt-2">
            {{ csrf_field() }}

            <div class="w-full md:w-1/2{{ $errors->has('expires_at') ? ' has-error' : '' }}">
                <label for="expires_at" class="label-normal-gray">{{ trans('rest-api::ui.keys.expires') }}</label>
                <input id="expires_at" type="date" class="input-normal-gray" name="expires_at" value="{{ old('expires_at') }}"
                       @if ($errors->has('expires_at')) aria-invalid="true" @endif>
                <span class="text-sm text-gray-500">{{ trans('rest-api::ui.keys.expires-hint') }}</span>
            </div>

            <div class="mt-5">
                <button type="submit" class="btn-normal bg-emerald-500 md:w-max">{{ trans('rest-api::ui.keys.btn.create') }}</button>
            </div>
        </form>
    @else
        <div class="mt-5">
            <div class="flex flex-wrap justify-between gap-2 p-2 odd:bg-slate-200">
                <div>{{ trans('rest-api::ui.keys.key') }}</div>
                <div class="font-mono">{{ $apiKey->key_prefix }}…</div>
            </div>
            <div class="flex flex-wrap justify-between gap-2 p-2 odd:bg-slate-200">
                <div>{{ trans('rest-api::ui.keys.status') }}</div>
                <div>{{ trans('rest-api::ui.keys.state.' . $apiKey->status()) }}</div>
            </div>
            <div class="flex flex-wrap justify-between gap-2 p-2 odd:bg-slate-200">
                <div>{{ trans('rest-api::ui.keys.created') }}</div>
                <div>{{ $apiKey->created_at->format('d.m.Y H:i') }}</div>
            </div>
            <div class="flex flex-wrap justify-between gap-2 p-2 odd:bg-slate-200">
                <div>{{ trans('rest-api::ui.keys.last-used') }}</div>
                <div>{{ $apiKey->last_used_at !== null ? $apiKey->last_used_at->format('d.m.Y H:i') : trans('rest-api::ui.keys.never') }}</div>
            </div>
            <div class="flex flex-wrap justify-between gap-2 p-2 odd:bg-slate-200">
                <div>{{ trans('rest-api::ui.keys.expires') }}</div>
                <div>{{ $apiKey->expires_at !== null ? $apiKey->expires_at->format('d.m.Y') : trans('rest-api::ui.keys.unlimited') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('plugin-rest-api.keys.regenerate') }}" class="mt-5"
              data-confirm="{{ trans('rest-api::ui.keys.confirm.regenerate') }}"
              data-confirm-title="{{ trans('rest-api::ui.keys.btn.regenerate') }}"
              data-confirm-button="{{ trans('rest-api::ui.keys.btn.regenerate') }}">
            {{ csrf_field() }}

            <div class="w-full md:w-1/2{{ $errors->has('expires_at') ? ' has-error' : '' }}">
                <label for="expires_at" class="label-normal-gray">{{ trans('rest-api::ui.keys.expires') }}</label>
                <input id="expires_at" type="date" class="input-normal-gray" name="expires_at"
                       value="{{ old('expires_at', $apiKey->expires_at?->format('Y-m-d')) }}"
                       @if ($errors->has('expires_at')) aria-invalid="true" @endif>
                <span class="text-sm text-gray-500">{{ trans('rest-api::ui.keys.expires-hint') }}</span>
            </div>

            <div class="mt-2">
                <button type="submit" class="btn-normal bg-emerald-500 md:w-max">{{ trans('rest-api::ui.keys.btn.regenerate') }}</button>
            </div>
        </form>

        <div class="mt-5 flex flex-wrap gap-2">
            @if (!$apiKey->isRevoked())
                <form method="POST" action="{{ route('plugin-rest-api.keys.revoke') }}"
                      data-confirm="{{ trans('rest-api::ui.keys.confirm.revoke') }}"
                      data-confirm-title="{{ trans('rest-api::ui.keys.btn.revoke') }}"
                      data-confirm-button="{{ trans('rest-api::ui.keys.btn.revoke') }}">
                    {{ csrf_field() }}
                    <button type="submit" class="btn-normal bg-orange-400 md:w-max">{{ trans('rest-api::ui.keys.btn.revoke') }}</button>
                </form>
            @endif

            <form method="POST" action="{{ route('plugin-rest-api.keys.delete') }}"
                  data-confirm="{{ trans('rest-api::ui.keys.confirm.delete') }}"
                  data-confirm-title="{{ trans('rest-api::ui.keys.btn.delete') }}"
                  data-confirm-button="{{ trans('rest-api::ui.keys.btn.delete') }}">
                {{ csrf_field() }}
                <button type="submit" class="btn-normal bg-red-500 md:w-max">{{ trans('rest-api::ui.keys.btn.delete') }}</button>
            </form>
        </div>
    @endif
@endsection
