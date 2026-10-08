import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref } from 'vue';

import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { profileQueryKeys } from '../queries/profileQueries';
import { createProfileRepository } from '../repositories/profileRepository';
import type { Profile, ProfileWritePayload } from '../schemas/profileSchemas';

const profileRepository = createProfileRepository();

export function useProfile() {
  const queryClient = useQueryClient();
  const saveError = ref<string | null>(null);

  const profileQuery = useQuery({
    queryKey: profileQueryKeys.detail(),
    queryFn: () => profileRepository.get(),
  });

  const updateProfileMutation = useMutation({
    mutationFn: (payload: ProfileWritePayload) => profileRepository.update(payload),
  });

  const profile = computed(() => profileQuery.data.value ?? null);
  const hasProfile = computed(() => profile.value !== null);
  const isLoading = computed(() => profileQuery.isLoading.value);
  const isSaving = computed(() => updateProfileMutation.isPending.value);
  const loadError = computed(() =>
    profileQuery.error.value
      ? resolveApiErrorMessage(profileQuery.error.value, 'No fue posible cargar el perfil.')
      : null,
  );

  async function loadProfile(): Promise<void> {
    await profileQuery.refetch();
  }

  async function updateProfile(payload: ProfileWritePayload): Promise<void> {
    saveError.value = null;

    try {
      const updatedProfile = await updateProfileMutation.mutateAsync(payload);
      queryClient.setQueryData<Profile>(profileQueryKeys.detail(), updatedProfile);
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible guardar el perfil.');
    }
  }

  return {
    profile,
    hasProfile,
    isLoading,
    isSaving,
    loadError,
    saveError,
    loadProfile,
    updateProfile,
  };
}
