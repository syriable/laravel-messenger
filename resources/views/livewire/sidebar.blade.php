<div class="msgr-rail__inner">
    <header class="msgr-rail__header">
        <div class="msgr-filter" role="tablist" aria-label="{{ __('messenger::ui.filter.label') }}">
            @foreach (\Syriable\Messenger\Livewire\Sidebar::SCOPES as $option)
                <button
                    type="button"
                    role="tab"
                    wire:click="setScope('{{ $option }}')"
                    @class(['msgr-filter__opt', 'msgr-filter__opt--active' => $scope === $option])
                    aria-selected="{{ $scope === $option ? 'true' : 'false' }}"
                >{{ __('messenger::ui.filter.'.$option) }}</button>
            @endforeach
        </div>

        <div class="msgr-search">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('messenger::ui.search_placeholder') }}"
                aria-label="{{ __('messenger::ui.search') }}"
            >
        </div>
    </header>

    <div class="msgr-rail__list" role="listbox" aria-label="{{ __('messenger::ui.conversations') }}">
        @forelse ($this->rows as $row)
            <button
                type="button"
                wire:key="conv-{{ $row['id'] }}"
                wire:click="select('{{ $row['id'] }}')"
                class="msgr-item-button"
                role="option"
                aria-selected="{{ $row['active'] ? 'true' : 'false' }}"
            >
                <x-messenger::conversation-list-item :row="$row" />
            </button>
        @empty
            <p class="msgr-rail__empty">
                {{ trim($search) !== '' ? __('messenger::ui.empty.no_results') : __('messenger::ui.empty.list') }}
            </p>
        @endforelse
    </div>
</div>
