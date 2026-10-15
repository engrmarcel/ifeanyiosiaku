"use client";

import { useState } from "react";

/**
 * Click-to-play video. Nothing is loaded from YouTube/Vimeo until the visitor presses play, so
 * there is no third-party tracking on page load. YouTube uses its privacy-enhanced domain.
 */
export function VideoEmbed({ provider, videoId, title }: { provider: "youtube" | "vimeo"; videoId: string; title: string }) {
  const [playing, setPlaying] = useState(false);

  const src =
    provider === "youtube"
      ? `https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}?autoplay=1&rel=0`
      : `https://player.vimeo.com/video/${encodeURIComponent(videoId)}?autoplay=1&dnt=1`;

  return (
    <div className="relative aspect-video overflow-hidden rounded-lg border border-border bg-surface">
      {playing ? (
        <iframe
          src={src}
          title={title}
          className="absolute inset-0 h-full w-full"
          allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
          referrerPolicy="strict-origin-when-cross-origin"
          sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
        />
      ) : (
        <button
          type="button"
          onClick={() => setPlaying(true)}
          className="group absolute inset-0 flex flex-col items-center justify-center gap-3 bg-[radial-gradient(circle_at_center,rgb(124_92_252/0.18),transparent_70%)]"
        >
          <span className="flex size-16 items-center justify-center rounded-full bg-accent text-accent-ink shadow-glow transition-transform group-hover:scale-105">
            <svg aria-hidden="true" viewBox="0 0 24 24" className="ml-1 size-7 fill-current">
              <path d="M8 5v14l11-7z" />
            </svg>
          </span>
          <span className="px-4 text-center text-sm text-text">
            Play video: {title}
            <span className="block text-xs text-muted">Loads from {provider === "youtube" ? "YouTube" : "Vimeo"}</span>
          </span>
        </button>
      )}
    </div>
  );
}
