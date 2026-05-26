import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Skeleton,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { api } from '../api';

export function ArticlePricesTable({ itemcode }: { itemcode: string | null }) {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['article-prices', itemcode],
    queryFn: () => api.listArticlePrices(itemcode ?? ''),
    enabled: Boolean(itemcode),
  });

  if (!itemcode) {
    return (
      <Typography variant="caption" color="text.secondary">
        Geen AFAS-itemcode gekoppeld aan deze base.
      </Typography>
    );
  }

  if (isError) {
    return <Alert severity="error">Kon prijzen niet laden: {(error as Error).message}</Alert>;
  }
  if (isLoading || !data) {
    return <Skeleton variant="rectangular" height={80} />;
  }
  if (data.length === 0) {
    return (
      <Typography variant="caption" color="text.secondary">
        Geen actieve prijzen voor <code>{itemcode}</code>.
      </Typography>
    );
  }

  return (
    <TableContainer>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Prijslijst</TableCell>
            <TableCell>Debiteur</TableCell>
            <TableCell>Staffel</TableCell>
            <TableCell align="right">Verkoopprijs</TableCell>
            <TableCell>Geldig vanaf</TableCell>
            <TableCell>Geldig tot</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {data.map((row, i) => (
            <TableRow key={`${row.prijslijstId}-${row.debiteurId ?? ''}-${row.staffelAantal ?? ''}-${row.geldigVan}-${i}`}>
              <TableCell>
                <code>{row.prijslijstId}</code>
              </TableCell>
              <TableCell>{row.debiteurId ?? '—'}</TableCell>
              <TableCell>{row.staffelAantal ?? '—'}</TableCell>
              <TableCell align="right">{row.verkoopprijsEur}</TableCell>
              <TableCell>{row.geldigVan}</TableCell>
              <TableCell>{row.geldigTot ?? '(open)'}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}
