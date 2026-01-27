import axios from 'axios';
import Sortable from 'sortablejs';

window.axios = axios;
window.Sortable = Sortable;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Copy text to clipboard helper
window.copyText = function(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text);
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
        document.body.removeChild(textArea);
        return Promise.resolve();
    }
};

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

import '@wotz/livewire-sortablejs';
