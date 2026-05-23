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

export const api = {
  listGroups: () => jsonGet<GroupSummary[]>('/api/groups'),
  showGroup: (familyHead: string) => jsonGet<GroupDetail>(`/api/groups/${encodeURIComponent(familyHead)}`),
};
