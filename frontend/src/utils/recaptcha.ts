type Grecaptcha = {
  ready: (callback: () => void) => void;
  execute: (siteKey: string, options: { action: string }) => Promise<string>;
};

type RecaptchaWindow = Window & {
  grecaptcha?: Grecaptcha;
};

const siteKey = process.env.REACT_APP_RECAPTCHA_SITE_KEY;

export const getRecaptchaToken = async (action: string): Promise<string | null> => {
  if (!siteKey || typeof window === 'undefined') {
    return null;
  }

  const recaptcha = (window as RecaptchaWindow).grecaptcha;
  if (!recaptcha?.ready || !recaptcha?.execute) {
    return null;
  }

  return new Promise((resolve) => {
    recaptcha.ready(async () => {
      try {
        const token = await recaptcha.execute(siteKey, { action });
        resolve(token || null);
      } catch {
        resolve(null);
      }
    });
  });
};
