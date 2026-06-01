<div class="msgr-root" style="block-size: 100%;">
    <x-messenger::shell :has-selection="filled($conversation)">
        <x-slot:rail>
            <livewire:messenger.sidebar :active-conversation-id="$conversation" />
        </x-slot:rail>

        <x-slot:thread>
            <livewire:messenger.thread :conversation-id="$conversation" :key="'thread-'.($conversation ?? 'none')" />
            <livewire:messenger.composer :conversation-id="$conversation" :key="'composer-'.($conversation ?? 'none')" />
        </x-slot:thread>

        <x-slot:empty-state>
            <x-messenger::empty-state />
        </x-slot:empty-state>
    </x-messenger::shell>
</div>
