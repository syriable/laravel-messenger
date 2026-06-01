{{--
    The 3-column messenger shell (rail · thread · profile).

    This is the static layout scaffold (Epic E2). The interactive regions are
    rendered as slots so the Livewire islands (Sidebar / Thread / Composer /
    ProfilePanel — Epics E3–E5) can be dropped in without changing the frame.
    A host can also fill the slots with its own markup.

    Slots: $rail, $thread, $profile. Optional: $emptyState shown when no
    conversation is selected.
--}}
@props([
    'theme' => config('messenger.ui.theme', 'neutral'),
    'style' => config('messenger.ui.message_style', 'flat'),
    'hasSelection' => false,
])

<div
    {{ $attributes->merge(['class' => 'msgr']) }}
    data-msgr-theme="{{ $theme }}"
    data-msgr-style="{{ $style }}"
    data-msgr-view="{{ $hasSelection ? 'thread' : 'list' }}"
>
    <aside class="msgr__rail" aria-label="{{ __('messenger::ui.conversations') }}">
        {{ $rail ?? '' }}
    </aside>

    <section class="msgr__thread" aria-label="{{ __('messenger::ui.conversation') }}">
        @if ($hasSelection)
            {{ $thread ?? '' }}
        @else
            {{ $emptyState ?? '' }}
        @endif
    </section>

    <aside class="msgr__profile" aria-label="{{ __('messenger::ui.about') }}">
        {{ $profile ?? '' }}
    </aside>
</div>
