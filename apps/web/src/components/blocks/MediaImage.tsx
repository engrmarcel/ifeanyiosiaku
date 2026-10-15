import type { Media } from "@/lib/cms/schemas";
import { cn } from "@/lib/cn";

/**
 * Responsive image from the media library. Uses the pre-generated WebP sizes, reserves space with
 * width/height (no layout shift) and loads lazily unless marked as a priority (above the fold).
 */
export function MediaImage({
  media,
  sizes = "(min-width: 1024px) 768px, 100vw",
  priority = false,
  className,
  alt,
}: {
  media: Media;
  sizes?: string;
  priority?: boolean;
  className?: string;
  alt?: string;
}) {
  const srcSet = media.variants.map((v) => `${v.url} ${v.width}w`);
  if (media.width) srcSet.push(`${media.url} ${media.width}w`);

  return (
    // eslint-disable-next-line @next/next/no-img-element -- media library already serves optimised sizes
    <img
      src={media.variants.find((v) => v.name === "lg")?.url ?? media.url}
      srcSet={srcSet.join(", ")}
      sizes={sizes}
      width={media.width ?? undefined}
      height={media.height ?? undefined}
      alt={alt ?? media.alt}
      loading={priority ? "eager" : "lazy"}
      fetchPriority={priority ? "high" : undefined}
      decoding="async"
      className={cn("h-auto max-w-full", className)}
    />
  );
}
