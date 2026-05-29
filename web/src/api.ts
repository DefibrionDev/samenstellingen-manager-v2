export interface GroupSummary {
  name: string;
  familyHead: string;
  baseCount: number;
  baseItemCount: number;
  familyHeadIsBase: boolean;
  modelNameNl: string | null;
  modelNameFr: string | null;
  modelNameEn: string | null;
}

export interface BaseItem {
  itemcode: string;
  label: string;
}

export interface GroupBase {
  id: number;
  name: string;
  languageCode: string;
  afasItemcode: string | null;
  variantLabel: string | null;
  items: BaseItem[];
}

export interface GroupDetail {
  familyHead: string;
  name: string;
  modelNameNl: string | null;
  modelNameFr: string | null;
  modelNameEn: string | null;
  bases: GroupBase[];
}

async function jsonGet<T>(path: string): Promise<T> {
  const response = await fetch(path);
  if (!response.ok) {
    throw new Error(`${response.status} ${response.statusText}`);
  }
  return (await response.json()) as T;
}

export interface Accessoire {
  itemcode: string;
  label: string;
  deltaCents?: number;
  deltaEur?: string;
  naamKortNl?: string | null;
  naamKortFr?: string | null;
  naamKortEn?: string | null;
}

export interface BlacklistEntry {
  itemcode: string;
  reason: string;
}

export interface GroupVariantRow {
  baseId: number;
  baseName: string;
  languageCode: string | null;
  accessoireItemcode: string | null;
  accessoireLabel: string | null;
  afasSamenstellingItemcode: string | null;
  afasStatus: string | null;
  canonicalName: string | null;
}

export interface SuspiciousBaseRow {
  afasItemcode: string;
  name: string;
  expectedAccessoireItemcode: string;
  expectedAccessoireLabel: string;
  bom: string[];
}

export interface NameDriftRow {
  afasItemcode: string;
  groupName: string;
  familyHead: string;
  baseName: string;
  languageCode: string;
  accessoireItemcode: string | null;
  accessoireLabel: string | null;
  expected: string;
  actual: string;
}

export interface MissingVariantRow {
  groupName: string;
  baseName: string;
  baseAfasSku: string;
  accessoireItemcode: string;
  accessoireLabel: string;
  expectedBom: string[];
  suggestedSku: string;
}

export const api = {
  listGroups: () => jsonGet<GroupSummary[]>('/api/groups'),
  showGroup: (familyHead: string) => jsonGet<GroupDetail>(`/api/groups/${encodeURIComponent(familyHead)}`),
  listAccessoires: () => jsonGet<Accessoire[]>('/api/accessoires'),
  listBlacklist: () => jsonGet<BlacklistEntry[]>('/api/bom-blacklist'),
  listGroupAccessoires: (familyHead: string) =>
    jsonGet<Accessoire[]>(`/api/groups/${encodeURIComponent(familyHead)}/accessoires`),
  listGroupVariants: (familyHead: string) =>
    jsonGet<GroupVariantRow[]>(`/api/groups/${encodeURIComponent(familyHead)}/variants`),
  listMissingVariants: () => jsonGet<MissingVariantRow[]>('/api/missing-variants'),
  listNameDrift: () => jsonGet<NameDriftRow[]>('/api/name-drift'),
  listSuspiciousBases: () => jsonGet<SuspiciousBaseRow[]>('/api/suspicious-bases'),
  listArticlePrices: (itemcode: string) =>
    jsonGet<ArticlePrice[]>(`/api/articles/${encodeURIComponent(itemcode)}/prices`),
  listPriceDrift: () => jsonGet<PriceDriftRow[]>('/api/price-drift'),
  listPrijslijstWhitelist: () =>
    jsonGet<PrijslijstWhitelistEntry[]>('/api/prijslijst-whitelist'),
  listDuplicateBoms: () => jsonGet<DuplicateBomGroup[]>('/api/duplicate-boms'),
  listStickerDrift: () => jsonGet<StickerDriftRow[]>('/api/sticker-drift'),
};

export interface StickerDriftRow {
  groupName: string;
  familyHeadItemcode: string;
  baseName: string;
  baseAfasItemcode: string;
  languageCode: string;
  expectedSticker: string;
  actualStickers: string[];
}

export interface DuplicateBomGroup {
  fingerprint: string;
  memberCount: number;
  members: Array<{ itemcode: string; name: string }>;
}

export interface PrijslijstWhitelistEntry {
  prijslijstId: string;
  omschrijving: string | null;
  reden: string;
  aangemaaktOp: string | null;
}

export interface PriceDriftRow {
  groupName: string;
  baseAfasItemcode: string;
  baseName: string;
  variantAfasItemcode: string;
  accessoireItemcode: string;
  accessoireLabel: string;
  expectedDeltaCents: number;
  expectedDeltaEur: string;
  prijslijstId: string;
  prijslijstOmschrijving: string | null;
  staffelAantal: number | null;
  basePrijsCents: number | null;
  basePrijsEur: string | null;
  variantPrijsCents: number | null;
  variantPrijsEur: string | null;
  actualDeltaCents: number | null;
  actualDeltaEur: string | null;
  status: 'toeslag-drift' | 'missing' | 'inconsistent-staffel';
}

export interface ArticlePrice {
  prijslijstId: string;
  debiteurId: string | null;
  verkoopprijsCents: number;
  verkoopprijsEur: string;
  staffelAantal: number | null;
  geldigVan: string;
  geldigTot: string | null;
}
