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
