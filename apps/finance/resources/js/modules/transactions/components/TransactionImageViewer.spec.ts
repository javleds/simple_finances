import PrimeVue from 'primevue/config';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import TransactionImageViewer from './TransactionImageViewer.vue';
const images = [
    { id: '1', name: 'Ticket', url: 'blob:ticket' },
    { id: '2', name: 'Factura', url: 'blob:invoice' },
    { id: '3', name: 'Recibo', url: '' },
];
function render(activeId: string | null = '1') {
    return mount(TransactionImageViewer, {
        props: { images, activeId },
        global: {
            plugins: [[PrimeVue, { unstyled: true }]],
            stubs: {
                AppModal: { template: '<div><slot/><slot name="footer"/></div>' },
                AppButton: { template: '<button><slot/></button>' },
            },
        },
    });
}
async function pointer(
    wrapper: ReturnType<typeof render>,
    type: string,
    id: number,
    x: number,
    y: number,
): Promise<void> {
    const stage = wrapper.get('[aria-label="Imagen del comprobante"]');
    const event = new Event(type, { bubbles: true, cancelable: true });
    Object.defineProperties(event, {
        pointerId: { value: id },
        clientX: { value: x },
        clientY: { value: y },
        button: { value: 0 },
    });
    stage.element.dispatchEvent(event);
    await wrapper.vm.$nextTick();
}
describe('private transaction image viewer', () => {
    it('navigates without wrapping and only responds to arrows while open', async () => {
        const wrapper = render();
        expect(wrapper.get('[aria-label="Imagen anterior"]').attributes('disabled')).toBeDefined();
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }));
        expect(wrapper.emitted('select')).toBeUndefined();
        await wrapper.get('[aria-label="Imagen siguiente"]').trigger('click');
        expect(wrapper.emitted('select')?.at(-1)).toEqual(['2']);
        await wrapper.setProps({ activeId: '3' });
        expect(
            wrapper.get('[aria-label="Imagen anterior"]').attributes('disabled'),
        ).toBeUndefined();
        expect(wrapper.get('[aria-label="Imagen siguiente"]').attributes('disabled')).toBeDefined();
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }));
        expect(wrapper.emitted('select')).toHaveLength(1);
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }));
        expect(wrapper.emitted('select')?.at(-1)).toEqual(['2']);
        await wrapper.setProps({ activeId: null });
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }));
        expect(wrapper.emitted('select')).toHaveLength(2);
        wrapper.unmount();
    });
    it('bounds zoom between 100 and 400 percent and resets on image change', async () => {
        const wrapper = render();
        for (let index = 0; index < 8; index++)
            await wrapper.get('[aria-label="Ampliar imagen"]').trigger('click');
        expect(wrapper.get('[aria-label="Nivel de zoom"]').text()).toBe('400%');
        expect(wrapper.get('[aria-label="Ampliar imagen"]').attributes('disabled')).toBeDefined();
        await wrapper.get('[aria-label="Restablecer imagen"]').trigger('click');
        expect(wrapper.get('[aria-label="Nivel de zoom"]').text()).toBe('100%');
        await wrapper.get('[aria-label="Ampliar imagen"]').trigger('click');
        await wrapper.setProps({ activeId: '2' });
        expect(wrapper.get('[aria-label="Nivel de zoom"]').text()).toBe('100%');
        wrapper.unmount();
    });
    it('swipes at natural zoom but uses drags to pan while enlarged', async () => {
        const wrapper = render('2');
        const stage = wrapper.get('[aria-label="Imagen del comprobante"]');
        stage.element.getBoundingClientRect = () => ({ width: 400, height: 400 }) as DOMRect;
        Object.defineProperties(wrapper.get('img').element, {
            clientWidth: { value: 400 },
            clientHeight: { value: 400 },
        });
        await pointer(wrapper, 'pointerdown', 1, 150, 100);
        await pointer(wrapper, 'pointerup', 1, 80, 100);
        expect(wrapper.emitted('select')?.at(-1)).toEqual(['3']);
        await wrapper.get('[aria-label="Ampliar imagen"]').trigger('click');
        await pointer(wrapper, 'pointerdown', 1, 100, 100);
        await pointer(wrapper, 'pointermove', 1, 150, 125);
        expect(wrapper.get('img').attributes('style')).toContain('translate(50px, 25px)');
        await pointer(wrapper, 'pointerup', 1, 150, 125);
        expect(wrapper.emitted('select')).toHaveLength(1);
        wrapper.unmount();
    });
    it('supports pinch zoom and does not interpret it as a swipe', async () => {
        const wrapper = render('2');
        await pointer(wrapper, 'pointerdown', 1, 100, 100);
        await pointer(wrapper, 'pointerdown', 2, 200, 100);
        await pointer(wrapper, 'pointermove', 2, 300, 100);
        expect(wrapper.get('[aria-label="Nivel de zoom"]').text()).toBe('200%');
        await pointer(wrapper, 'pointerup', 2, 300, 100);
        await pointer(wrapper, 'pointerup', 1, 100, 100);
        expect(wrapper.emitted('select')).toBeUndefined();
        wrapper.unmount();
    });
    it('shows loading without an empty image URL and delegates download and close', async () => {
        const wrapper = render('3');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('Cargando imagen');
        await wrapper.get('[aria-label="Descargar imagen"]').trigger('click');
        expect(wrapper.emitted('download')?.[0]).toEqual(['3']);
        const close = wrapper.findAll('button').find((button) => button.text() === 'Cerrar visor');
        await close!.trigger('click');
        expect(wrapper.emitted('close')).toHaveLength(1);
        wrapper.unmount();
    });
});

it('closes when the active image is removed', async () => {
    const wrapper = render('2');
    await wrapper.setProps({ images: images.filter((image) => image.id !== '2') });
    expect(wrapper.emitted('close')).toHaveLength(1);
    wrapper.unmount();
});

it('offers retry for a failed image instead of showing an endless loading state', async () => {
    const wrapper = render('3');
    await wrapper.setProps({
        images: images.map((image) => ({ ...image, failed: image.id === '3' })),
    });
    expect(wrapper.text()).toContain('No fue posible cargar esta imagen.');
    expect(wrapper.text()).not.toContain('Cargando imagen');
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Reintentar imagen')!
        .trigger('click');
    expect(wrapper.emitted('retry')?.[0]).toEqual(['3']);
    wrapper.unmount();
});

it.each([
    { width: 80, height: 400, expected: 'translate(0px, 100px)' },
    { width: 400, height: 80, expected: 'translate(100px, 0px)' },
])('keeps narrow images visible when dragging at zoom', async ({ width, height, expected }) => {
    const wrapper = render();
    wrapper.get('[aria-label="Imagen del comprobante"]').element.getBoundingClientRect = () =>
        ({ width: 400, height: 400 }) as DOMRect;
    Object.defineProperties(wrapper.get('img').element, {
        clientWidth: { value: width },
        clientHeight: { value: height },
    });
    await wrapper.get('[aria-label="Ampliar imagen"]').trigger('click');
    await pointer(wrapper, 'pointerdown', 1, 100, 100);
    await pointer(wrapper, 'pointermove', 1, 900, 900);
    expect(wrapper.get('img').attributes('style')).toContain(expected);
    await wrapper.get('img').trigger('load');
    expect(wrapper.get('img').attributes('style')).toContain(expected);
    wrapper.unmount();
});

it('reclamps the image after viewport resizing and disconnects its observer', async () => {
    let resize: (() => void) | undefined;
    const disconnect = vi.fn();
    vi.stubGlobal(
        'ResizeObserver',
        class {
            constructor(callback: () => void) {
                resize = callback;
            }
            observe(): void {}
            disconnect = disconnect;
        },
    );
    const wrapper = render();
    await wrapper.vm.$nextTick();
    let stageWidth = 400;
    wrapper.get('[aria-label="Imagen del comprobante"]').element.getBoundingClientRect = () =>
        ({ width: stageWidth, height: 400 }) as DOMRect;
    Object.defineProperties(wrapper.get('img').element, {
        clientWidth: { value: 400 },
        clientHeight: { value: 400 },
    });
    await wrapper.get('[aria-label="Ampliar imagen"]').trigger('click');
    await pointer(wrapper, 'pointerdown', 1, 100, 100);
    await pointer(wrapper, 'pointermove', 1, 200, 100);
    expect(wrapper.get('img').attributes('style')).toContain('translate(100px, 0px)');
    stageWidth = 600;
    resize?.();
    await wrapper.vm.$nextTick();
    expect(wrapper.get('img').attributes('style')).toContain('translate(0px, 0px)');
    wrapper.unmount();
    expect(disconnect).toHaveBeenCalled();
    vi.unstubAllGlobals();
});

it('handles Escape before underlying dialogs and leaves it untouched while closed', async () => {
    const wrapper = render();
    const underlying = vi.fn();
    document.addEventListener('keydown', underlying);
    const escape = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    document.dispatchEvent(escape);
    expect(wrapper.emitted('close')).toHaveLength(1);
    expect(escape.defaultPrevented).toBe(true);
    expect(underlying).not.toHaveBeenCalled();
    await wrapper.setProps({ activeId: null });
    const nextEscape = new KeyboardEvent('keydown', {
        key: 'Escape',
        bubbles: true,
        cancelable: true,
    });
    document.dispatchEvent(nextEscape);
    expect(nextEscape.defaultPrevented).toBe(false);
    expect(underlying).toHaveBeenCalledTimes(1);
    document.removeEventListener('keydown', underlying);
    wrapper.unmount();
});
