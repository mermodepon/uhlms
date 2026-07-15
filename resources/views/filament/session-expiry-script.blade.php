<script @if(request()->attributes->get('csp_nonce')) nonce="{{ request()->attributes->get('csp_nonce') }}" @endif>
    const cspNonce = document.currentScript?.nonce;

    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ options, fail }) => {
            if (cspNonce) {
                options.headers['X-CSP-Nonce'] = cspNonce;
            }

            fail(({ status, preventDefault }) => {
                if (status === 419) {
                    preventDefault();
                    window.location.href = '/admin/login';
                }
            });
        });
    });
</script>
