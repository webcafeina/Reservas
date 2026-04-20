import { useMutation, useQuery } from '@tanstack/react-query';

import { api } from './client';
import type { UserProfile } from '../types/profile';

interface ProfileResponse {
    profile: UserProfile | null;
}

export function useProfile(enabled: boolean) {
    return useQuery({
        queryKey: ['profile'],
        queryFn: () => api.get<ProfileResponse>('/user/profile'),
        enabled,
    });
}

export function useUpdateProfile() {
    return useMutation({
        mutationFn: (payload: Partial<UserProfile>) =>
            api.put<ProfileResponse>('/user/profile', payload),
    });
}
