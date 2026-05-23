export interface GroupSummary {
  name: string;
  familyHead: string;
  baseCount: number;
  baseItemCount: number;
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
  items: BaseItem[];
}

export interface GroupDetail {
  familyHead: string;
  name: string;
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
};
