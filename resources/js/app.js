import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { Html5Qrcode } from 'html5-qrcode';

window.Alpine = Alpine;
window.Chart = Chart;
window.Html5Qrcode = Html5Qrcode;

const stored = localStorage.getItem('adnf-theme');
if (stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
}

Alpine.data('theme', () => ({
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('adnf-theme', this.dark ? 'dark' : 'light');
    },
}));

// Evita envíos dobles: desactiva el botón y muestra estado de carga.
Alpine.data('guardedForm', () => ({
    busy: false,
    submit(e) {
        if (this.busy) {
            e.preventDefault();
            return;
        }
        this.busy = true;
    },
}));

Alpine.data('confirmAction', () => ({ open: false }));

Alpine.data('firstUseGuide', (config) => ({
    steps: config.steps,
    seen: config.seen,
    index: 0,
    saving: false,
    error: '',
    init() {
        if (!this.seen) this.$nextTick(() => this.show());
    },
    show() {
        this.index = 0;
        this.error = '';
        if (!this.$refs.dialog.open) this.$refs.dialog.showModal();
    },
    async finish() {
        if (this.saving) return;
        if (this.seen) {
            this.$refs.dialog.close();
            return;
        }
        this.saving = true;
        this.error = '';
        try {
            const response = await fetch(config.url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            if (!response.ok || (await response.json()).ok !== true) throw new Error('No se pudo guardar el progreso.');
            this.seen = true;
            this.$refs.dialog.close();
        } catch (error) {
            this.error = 'No se pudo guardar que viste la guía. Comprueba tu conexión e inténtalo de nuevo.';
        } finally {
            this.saving = false;
        }
    },
}));

Alpine.data('scanner', (config) => ({
    cfg: config,
    state: 'idle', // idle | scanning | loading | found | error | done
    message: '',
    card: null,
    rewardEarned: false,
    cycleWithoutReward: false,
    qr: null,
    last: { text: '', at: 0 },
    manual: '',
    form: { quantity: 1, amount: '', rule_id: '', reference: '' },
    key: crypto.randomUUID(),
    branch: config.branch || '',
    busy: false,
    async start() {
        this.message = '';
        try {
            this.qr = new Html5Qrcode('reader');
            this.state = 'scanning';
            await this.qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, (t) => this.onRead(t), () => {});
        } catch (e) {
            this.state = 'idle';
            this.message = 'No se pudo acceder a la cámara. Usa un lector USB o escribe el código.';
        }
    },
    async stop() {
        if (this.qr && this.qr.isScanning) {
            await this.qr.stop();
        }
    },
    async onRead(text) {
        const now = Date.now();
        if (text === this.last.text && now - this.last.at < 4000) return; // lecturas repetidas accidentales
        this.last = { text, at: now };
        await this.stop();
        await this.lookup(text);
    },
    async submitManual() {
        if (this.manual.trim()) {
            await this.lookup(this.manual.trim());
            this.manual = '';
        }
    },
    headers() {
        return {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        };
    },
    async lookup(code) {
        this.rewardEarned = false;
        this.cycleWithoutReward = false;
        this.state = 'loading';
        const res = await fetch(this.cfg.lookupUrl, { method: 'POST', headers: this.headers(), body: JSON.stringify({ code }) });
        const data = await res.json().catch(() => ({ message: 'Respuesta inválida del servidor.' }));
        if (!res.ok) {
            this.state = 'error';
            this.message = data.message || 'No se pudo identificar la tarjeta.';
            return;
        }
        this.card = data.card;
        this.key = crypto.randomUUID();
        this.form = { quantity: 1, amount: '', rule_id: data.card.rules[0]?.id || '', reference: '' };
        this.state = 'found';
        this.message = 'Tarjeta identificada correctamente.';
    },
    async award() {
        if (this.busy) return;
        this.busy = true;
        const res = await fetch(this.cfg.awardUrl, {
            method: 'POST',
            headers: this.headers(),
            body: JSON.stringify({ card: this.card.token, idempotency_key: this.key, branch_id: this.branch || null, ...this.form }),
        });
        const data = await res.json().catch(() => ({ message: 'Respuesta inválida del servidor.' }));
        this.busy = false;
        if (!res.ok) {
            this.state = 'error';
            this.message = data.message || 'No se pudo registrar la operación.';
            return;
        }
        this.card = data.card;
        this.message = data.message;
        this.rewardEarned = data.new_entitlements > 0;
        this.cycleWithoutReward = data.cycle_completed && !this.rewardEarned;
        this.state = 'done';
        if (navigator.vibrate) navigator.vibrate(120);
    },
    async redeem(id) {
        if (this.busy) return;
        this.busy = true;
        const res = await fetch(this.cfg.redeemUrl, {
            method: 'POST',
            headers: this.headers(),
            body: JSON.stringify({ entitlement: id, branch_id: this.branch || null }),
        });
        const data = await res.json().catch(() => ({ message: 'Respuesta inválida del servidor.' }));
        this.busy = false;
        if (!res.ok) {
            this.state = 'error';
            this.message = data.message || 'No se pudo canjear.';
            return;
        }
        this.card = data.card;
        this.message = data.message;
        this.rewardEarned = false;
        this.cycleWithoutReward = false;
        this.state = 'done';
    },
    reset() {
        this.card = null;
        this.rewardEarned = false;
        this.cycleWithoutReward = false;
        this.message = '';
        this.state = 'idle';
    },
}));

Alpine.data('statsChart', (cfg) => ({
    chart: null,
    init() {
        this.chart = new Chart(this.$refs.canvas, {
            type: 'line',
            data: {
                labels: cfg.labels,
                datasets: [
                    { label: 'Sellos', data: cfg.stamps, borderColor: '#0FA7B4', backgroundColor: 'rgba(15,167,180,.15)', fill: true, tension: 0.3 },
                    { label: 'Clientes nuevos', data: cfg.customers, borderColor: '#9CA3AF', tension: 0.3 },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    },
}));

Alpine.start();
