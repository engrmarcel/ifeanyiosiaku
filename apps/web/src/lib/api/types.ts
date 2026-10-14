export type CurrentUser = {
  id: string;
  name: string;
  email: string;
  email_verified: boolean;
  timezone: string;
  two_factor_enabled: boolean;
  is_staff: boolean;
  can_access_admin: boolean;
  roles: string[];
  profile: {
    display_name: string;
    handle: string | null;
    visibility: "public" | "members" | "private";
    show_on_leaderboard: boolean;
  } | null;
};

export type AccountSession = {
  id: string;
  ip_address: string | null;
  user_agent: string | null;
  last_active_at: string;
  is_current: boolean;
};

export type CaptchaConfig = {
  driver: "first_party" | "recaptcha" | "turnstile";
  site_key?: string | null;
  algorithm?: string;
  token_field: string;
  honeypot_field: string;
  login_requires_captcha: boolean;
};

export type CaptchaChallenge = {
  challenge: string;
  difficulty: number;
  algorithm: "SHA-256";
  expires_at: string;
};

export type DataEnvelope<T> = { data: T };
