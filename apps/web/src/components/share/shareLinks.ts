/**
 * Share links for social networks. Plain links to each network's own share page: no third-party
 * scripts, buttons or tracking pixels are loaded on our site.
 */

export type ShareTarget = "linkedin" | "x" | "facebook" | "whatsapp" | "telegram" | "email";

export type ShareLink = { network: ShareTarget; label: string; href: string };

export function shareLinks(url: string, title: string): ShareLink[] {
  const u = encodeURIComponent(url);
  const t = encodeURIComponent(title);
  const both = encodeURIComponent(`${title} ${url}`);

  return [
    { network: "linkedin", label: "LinkedIn", href: `https://www.linkedin.com/sharing/share-offsite/?url=${u}` },
    { network: "x", label: "X", href: `https://x.com/intent/post?url=${u}&text=${t}` },
    { network: "facebook", label: "Facebook", href: `https://www.facebook.com/sharer/sharer.php?u=${u}` },
    { network: "whatsapp", label: "WhatsApp", href: `https://wa.me/?text=${both}` },
    { network: "telegram", label: "Telegram", href: `https://t.me/share/url?url=${u}&text=${t}` },
    { network: "email", label: "Email", href: `mailto:?subject=${t}&body=${both}` },
  ];
}
