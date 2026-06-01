import { useQuery } from '@tanstack/react-query';
import { Alert, Paper, Skeleton, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Typography } from '@mui/material';
import { api, Website } from '../api';

function maskUuid(uuid: string): string {
  if (uuid.length <= 12) return uuid;
  return `${uuid.slice(0, 8)}…${uuid.slice(-4)}`;
}

export function WebsiteSettings() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['websites'],
    queryFn: api.listWebsites,
  });

  if (isError) {
    return <Alert severity="error">Kon websites niet laden: {(error as Error).message}</Alert>;
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        Websites
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Per website wordt het free-field-paar in AFAS vastgelegd dat de publicatie-staat regelt
        (Sync_*/Tonen_*). Toevoegen, wijzigen en verwijderen gaat via de CLI:
        {' '}
        <code>bin/samenstellingen website:add</code>,{' '}
        <code>website:list</code>,{' '}
        <code>website:remove</code>.
      </Typography>
      {isLoading || !data ? (
        <Skeleton variant="rectangular" height={200} />
      ) : data.length === 0 ? (
        <Alert severity="info">
          Nog geen websites geregistreerd. Voeg er één toe met
          {' '}
          <code>bin/samenstellingen website:add &lt;naam&gt; &lt;sync-uuid&gt; &lt;tonen-uuid&gt;</code>.
        </Alert>
      ) : (
        <Paper>
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Naam</TableCell>
                  <TableCell>FF Sync UUID</TableCell>
                  <TableCell>FF Tonen UUID</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.map((w: Website) => (
                  <TableRow key={w.id}>
                    <TableCell>{w.name}</TableCell>
                    <TableCell>
                      <code title={w.ffSyncUuid}>{maskUuid(w.ffSyncUuid)}</code>
                    </TableCell>
                    <TableCell>
                      <code title={w.ffTonenUuid}>{maskUuid(w.ffTonenUuid)}</code>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}
    </Stack>
  );
}
