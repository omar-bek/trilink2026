<?php

namespace App\Enums;

use App\Services\ContractService;

/**
 * The legal system that governs a company's contracts.
 *
 * The UAE has THREE parallel legal systems running side-by-side:
 *
 *   - FEDERAL — UAE federal civil code (Federal Decree-Law 50/2022,
 *     Federal Law 5/1985), with disputes heard by the federal/onshore
 *     court system. This covers every mainland LLC and most free
 *     zones outside DIFC and ADGM.
 *
 *   - DIFC — Dubai International Financial Centre operates under its
 *     own English-style common-law jurisdiction (DIFC Contract Law,
 *     DIFC Companies Law), with disputes heard by DIFC Courts. Any
 *     contract between two DIFC entities defaults to DIFC law unless
 *     the parties expressly opt out.
 *
 *   - ADGM — Abu Dhabi Global Market is the second common-law island.
 *     ADGM Application Regulations (Common Law) 2015 incorporates
 *     English common law and equity directly. Disputes go to ADGM
 *     Courts.
 *
 * The {@see ContractService::buildBilingualUaeContractTerms()}
 * dispatcher routes to the right clause-set generator based on the
 * combined jurisdiction of the buyer and supplier on the contract.
 */
enum LegalJurisdiction: string
{
    case FEDERAL = 'federal';
    case DIFC = 'difc';
    case ADGM = 'adgm';

    public function label(): string
    {
        return match ($this) {
            self::FEDERAL => 'UAE Federal',
            self::DIFC => 'DIFC',
            self::ADGM => 'ADGM',
        };
    }

    /**
     * Decide which legal system governs a contract whose parties live
     * in two (potentially different) jurisdictions. The rules:
     *
     *   - If BOTH parties are in the same common-law jurisdiction
     *     (DIFC-DIFC or ADGM-ADGM), use that.
     *   - If only ONE party is in a common-law jurisdiction, the
     *     contract STILL defaults to federal law because mixed
     *     contracts are weak in either common-law court without an
     *     express opt-in clause. Federal is the safer default.
     *   - Otherwise (two federal parties, or unknown), federal.
     */
    public static function resolveForPair(?self $buyer, ?self $supplier): self
    {
        $buyer = $buyer ?? self::FEDERAL;
        $supplier = $supplier ?? self::FEDERAL;

        if ($buyer === self::DIFC && $supplier === self::DIFC) {
            return self::DIFC;
        }
        if ($buyer === self::ADGM && $supplier === self::ADGM) {
            return self::ADGM;
        }

        return self::FEDERAL;
    }
}
