import { computed, onBeforeUnmount, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';

type UseInfiniteScrollOptions = {
  enabled?: MaybeRefOrGetter<boolean>;
  rootMargin?: string;
  threshold?: number;
  onIntersect: () => void;
};

export function useInfiniteScroll(options: UseInfiniteScrollOptions) {
  const target = ref<HTMLElement | null>(null);
  const isEnabled = computed(() => toValue(options.enabled) ?? true);
  let observer: IntersectionObserver | null = null;

  function disconnectObserver(): void {
    observer?.disconnect();
    observer = null;
  }

  function connectObserver(): void {
    disconnectObserver();

    if (typeof window === 'undefined' || !target.value || !isEnabled.value) {
      return;
    }

    observer = new IntersectionObserver(
      (entries) => {
        const [entry] = entries;

        if (!entry?.isIntersecting || !isEnabled.value) {
          return;
        }

        options.onIntersect();
      },
      {
        root: null,
        rootMargin: options.rootMargin ?? '0px 0px 320px 0px',
        threshold: options.threshold ?? 0,
      },
    );

    observer.observe(target.value);
  }

  watch([target, isEnabled], connectObserver, { immediate: true });
  onBeforeUnmount(disconnectObserver);

  return {
    target,
  };
}
