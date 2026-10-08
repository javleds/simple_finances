import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';

import { mapProfileApiToDomain, profileApiSchema } from '../schemas/profileSchemas';
import type { Profile, ProfileWritePayload } from '../schemas/profileSchemas';

const apiClient = createApiClient();

const singleProfileSchema = z
  .union([
    profileApiSchema,
    z.object({
      data: profileApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

function buildWritePayload(payload: ProfileWritePayload) {
  return {
    name: payload.name,
    email: payload.email,
    phone_number: payload.phoneNumber,
    password: payload.password,
    password_confirmation: payload.passwordConfirmation,
  };
}

export function createProfileRepository() {
  return {
    async get(): Promise<Profile> {
      const response = await apiClient.get<unknown>('/profile');
      return mapProfileApiToDomain(singleProfileSchema.parse(response));
    },
    async update(payload: ProfileWritePayload): Promise<Profile> {
      const response = await apiClient.put<unknown>('/profile', buildWritePayload(payload));
      return mapProfileApiToDomain(singleProfileSchema.parse(response));
    },
  };
}
