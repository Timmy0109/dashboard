/**
 * User-related types — kept aligned with backend `users` table + auth store.
 * additive-only: no breaking changes to existing fields.
 */

export type Role = 'admin' | 'manager' | 'member'
export type UserStatus = 'pending' | 'active' | 'inactive' | 'suspended'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  status?: UserStatus
  avatar_url: string | null
  company_id?: number | null
  company_name?: string | null
  last_login_at?: string | null
  created_at?: string
}

/** Minimal user shape used in chips, avatars, @mention dropdowns. */
export interface UserSummary {
  id: number
  name: string
  email?: string
  avatar_url?: string | null
  role?: Role
}
