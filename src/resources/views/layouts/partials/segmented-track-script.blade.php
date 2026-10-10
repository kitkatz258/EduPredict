{{-- Classic script so the helper exists before Livewire starts Alpine. Module bundles run later. --}}
<script>
    window.segmentedTrack = function () {
        return {
            left: 0,
            top: 0,
            width: 0,
            height: 0,
            ready: false,
            place(animate) {
                const active = this.$refs.track?.querySelector('[aria-selected="true"]');
                const indicator = this.$refs.indicator;
                if (!active || !indicator) {
                    return;
                }
                if (!animate) {
                    indicator.style.transition = 'none';
                }
                this.left = active.offsetLeft;
                this.top = active.offsetTop;
                this.width = active.offsetWidth;
                this.height = active.offsetHeight;
                this.ready = true;
                if (!animate) {
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            indicator.style.transition = '';
                        });
                    });
                }
            },
            init() {
                this.$nextTick(() => this.place(false));
                this._observer = new MutationObserver(() => this.place(true));
                this._observer.observe(this.$refs.track, {
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['aria-selected'],
                });
                this._onResize = () => this.place(false);
                window.addEventListener('resize', this._onResize);
            },
            destroy() {
                this._observer?.disconnect();
                window.removeEventListener('resize', this._onResize);
            },
            select(tab) {
                if (!tab || !this.$refs.track) {
                    return;
                }
                this.$refs.track.querySelectorAll('[role="tab"]').forEach((el) => {
                    const on = el === tab;
                    el.setAttribute('aria-selected', on ? 'true' : 'false');
                    el.classList.toggle('text-brand-900', on);
                    el.classList.toggle('text-gray-700', !on);
                });
            },
            focusNext(step) {
                const tabs = [...this.$refs.track.querySelectorAll('[role="tab"]')];
                if (tabs.length === 0) {
                    return;
                }
                let index = tabs.findIndex((tab) => tab === document.activeElement);
                if (index < 0) {
                    index = tabs.findIndex((tab) => tab.getAttribute('aria-selected') === 'true');
                }
                const next = tabs[(index + step + tabs.length) % tabs.length];
                next?.focus();
                next?.click();
            },
        };
    };
</script>
