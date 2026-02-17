<x-button class="btn-circle btn-ghost btn-xs" x-data="{text: '{{ $text }}', showSuccess: false, timeout: null, copy() {
    window.copyText(this.text);
    this.showSuccess = true;

    if (this.timeout) clearTimeout(this.timeout);
    this.timeout = setTimeout(() => {
        this.showSuccess = false;
    }, 500);
}}" x-on:click="copy"> 
    <x-icon name="o-document-duplicate" x-show="!showSuccess" />
    <x-icon name="o-check" x-show="showSuccess" x-cloak />
</x-button>
