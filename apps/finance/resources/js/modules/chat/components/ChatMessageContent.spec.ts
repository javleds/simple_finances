import { mount } from '@vue/test-utils';
import { expect, it } from 'vitest';
import ChatMessageContent from './ChatMessageContent.vue';
it('renders limited emphasis while treating HTML and links as untrusted text', () => {
    const wrapper = mount(ChatMessageContent, {
        props: { content: '**Total**\n<script>alert(1)</script>\n[click](javascript:alert(1))' },
    });
    expect(wrapper.find('strong').text()).toBe('Total');
    expect(wrapper.find('script').exists()).toBe(false);
    expect(wrapper.find('a').exists()).toBe(false);
    expect(wrapper.text()).toContain('<script>');
});
