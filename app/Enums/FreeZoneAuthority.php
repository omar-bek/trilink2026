<?php

namespace App\Enums;

/**
 * The free-zone authority that licensed a UAE company.
 *
 * Each free zone has its own licensing body, its own permitted
 * activities, and its own VAT classification (Designated vs
 * non-Designated under Cabinet Decision 59/2017). The list below
 * is the consolidated set of zones a B2B procurement platform
 * commonly sees in production. Add new entries by appending — never
 * renumber, because the string value is what's stored in the DB.
 *
 * Categorisation tags carried by helpers below:
 *
 *   - DESIGNATED zones (VAT 0% on goods between two designated zones,
 *     reverse-charge to mainland) — listed by Cabinet Decision 59/2017
 *     as updated. The constant {@see DESIGNATED_ZONES} is the source
 *     of truth.
 *
 *   - COMMON-LAW zones — DIFC and ADGM are simultaneously free zones
 *     AND independent legal jurisdictions, so they need both this
 *     enum and the LegalJurisdiction enum.
 */
enum FreeZoneAuthority: string
{
    // Dubai
    case DAFZA = 'dafza';            // Dubai Airport Free Zone Authority
    case JAFZA = 'jafza';            // Jebel Ali Free Zone Authority
    case DMCC = 'dmcc';             // Dubai Multi Commodities Centre
    case DIFC = 'difc';             // Dubai International Financial Centre
    case DSO = 'dso';              // Dubai Silicon Oasis
    case DUBAI_SOUTH = 'dubai_south';      // Dubai South / DWC
    case IFZA = 'ifza';             // International Free Zone Authority

    // Abu Dhabi
    case ADGM = 'adgm';             // Abu Dhabi Global Market
    case KIZAD = 'kizad';            // Khalifa Industrial Zone Abu Dhabi
    case MASDAR = 'masdar';           // Masdar City
    case TWOFOUR54 = 'twofour54';        // twofour54 Media Free Zone

    // Sharjah
    case SAIF_ZONE = 'saif_zone';        // Sharjah Airport International Free Zone
    case HAMRIYAH = 'hamriyah';         // Hamriyah Free Zone
    case SHAMS = 'shams';            // Sharjah Media City
    case SRTIP = 'srtip';            // Sharjah Research Tech & Innovation Park

    // Northern emirates
    case RAKEZ = 'rakez';            // Ras Al Khaimah Economic Zone
    case AJMAN_FZ = 'ajman_fz';         // Ajman Free Zone
    case UAQ_FZ = 'uaq_fz';           // Umm Al Quwain Free Trade Zone
    case FUJAIRAH_FZ = 'fujairah_fz';      // Fujairah Free Zone

    case OTHER = 'other';

    /**
     * Designated Zones for VAT purposes per Cabinet Decision 59/2017.
     * Goods supplied between two Designated Zones are treated as
     * outside the scope of UAE VAT (effectively 0%). Goods leaving a
     * Designated Zone for the mainland are subject to standard 5% VAT
     * with reverse-charge mechanics. The list is amended periodically
     * by FTA — keep this constant in sync with the published list.
     */
    public const DESIGNATED_ZONES = [
        self::DAFZA,
        self::JAFZA,
        self::DMCC,
        self::DUBAI_SOUTH,
        self::KIZAD,
        self::MASDAR,
        self::SAIF_ZONE,
        self::HAMRIYAH,
        self::RAKEZ,
        self::AJMAN_FZ,
        self::UAQ_FZ,
        self::FUJAIRAH_FZ,
    ];

    public function label(): string
    {
        return match ($this) {
            self::DAFZA => 'DAFZA — Dubai Airport Free Zone',
            self::JAFZA => 'JAFZA — Jebel Ali Free Zone',
            self::DMCC => 'DMCC — Dubai Multi Commodities Centre',
            self::DIFC => 'DIFC — Dubai International Financial Centre',
            self::DSO => 'Dubai Silicon Oasis',
            self::DUBAI_SOUTH => 'Dubai South / DWC',
            self::IFZA => 'IFZA — International Free Zone Authority',
            self::ADGM => 'ADGM — Abu Dhabi Global Market',
            self::KIZAD => 'KIZAD — Khalifa Industrial Zone',
            self::MASDAR => 'Masdar City',
            self::TWOFOUR54 => 'twofour54 Media Free Zone',
            self::SAIF_ZONE => 'SAIF Zone — Sharjah Airport Free Zone',
            self::HAMRIYAH => 'Hamriyah Free Zone',
            self::SHAMS => 'SHAMS — Sharjah Media City',
            self::SRTIP => 'SRTIP — Sharjah Research Park',
            self::RAKEZ => 'RAKEZ — Ras Al Khaimah Economic Zone',
            self::AJMAN_FZ => 'Ajman Free Zone',
            self::UAQ_FZ => 'Umm Al Quwain Free Trade Zone',
            self::FUJAIRAH_FZ => 'Fujairah Free Zone',
            self::OTHER => 'Other',
        };
    }

    /**
     * Whether this zone is a VAT Designated Zone — see the constant
     * docblock above for the legal background.
     */
    public function isDesignated(): bool
    {
        return in_array($this, self::DESIGNATED_ZONES, true);
    }

    /**
     * The legal jurisdiction tied to this zone. Most zones inherit
     * federal law; DIFC and ADGM are the two common-law exceptions.
     */
    public function jurisdiction(): LegalJurisdiction
    {
        return match ($this) {
            self::DIFC => LegalJurisdiction::DIFC,
            self::ADGM => LegalJurisdiction::ADGM,
            default => LegalJurisdiction::FEDERAL,
        };
    }
}
