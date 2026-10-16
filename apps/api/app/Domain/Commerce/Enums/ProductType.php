<?php

namespace App\Domain\Commerce\Enums;

/**
 * Kinds of product (REQ-COM-1). The downloadable kinds are created in the store admin; courses,
 * event tickets, appointments and premium articles become products through their own modules.
 */
enum ProductType: string
{
    case Ebook = 'ebook';
    case Pdf = 'pdf';
    case Research = 'research';
    case Publication = 'publication';
    case Template = 'template';
    case CodePackage = 'code_package';
    case Asset = 'asset';
    case Bundle = 'bundle';
    case Custom = 'custom';
    case PremiumArticle = 'premium_article';
    case Course = 'course';
    case EventTicket = 'event_ticket';
    case Appointment = 'appointment';

    public function label(): string
    {
        return match ($this) {
            self::Ebook => 'eBook',
            self::Pdf => 'PDF',
            self::Research => 'Research paper',
            self::Publication => 'Publication',
            self::Template => 'Template',
            self::CodePackage => 'Code package',
            self::Asset => 'Digital asset',
            self::Bundle => 'Bundle',
            self::Custom => 'Digital product',
            self::PremiumArticle => 'Premium article',
            self::Course => 'Course',
            self::EventTicket => 'Event ticket',
            self::Appointment => 'Appointment',
        };
    }

    /** Whether buyers receive downloadable files. */
    public function isDownloadable(): bool
    {
        return in_array($this, [self::Ebook, self::Pdf, self::Research, self::Publication, self::Template, self::CodePackage, self::Asset, self::Custom], true);
    }

    /**
     * Types an editor can create directly in Store → Products.
     *
     * @return array<string, string>
     */
    public static function storeCreatable(): array
    {
        $options = [];
        foreach ([self::Ebook, self::Pdf, self::Research, self::Publication, self::Template, self::CodePackage, self::Asset, self::Bundle, self::Custom] as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
