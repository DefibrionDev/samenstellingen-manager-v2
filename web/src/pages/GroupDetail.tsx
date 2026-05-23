import { useQuery } from '@tanstack/react-query';
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Alert,
  Breadcrumbs,
  Chip,
  Link as MuiLink,
  Paper,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import { Link as RouterLink, useParams } from 'react-router-dom';
import { api } from '../api';

export function GroupDetail() {
  const { familyHead } = useParams<{ familyHead: string }>();
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['group', familyHead],
    queryFn: () => api.showGroup(familyHead ?? ''),
    enabled: Boolean(familyHead),
  });

  if (isError) {
    const status = (error as Error).message.startsWith('404') ? 'Groep niet gevonden.' : (error as Error).message;
    return <Alert severity="error">{status}</Alert>;
  }

  return (
    <Stack spacing={3}>
      <Breadcrumbs>
        <MuiLink component={RouterLink} to="/" underline="hover" color="inherit">
          Groepen
        </MuiLink>
        <Typography color="text.primary">{data?.name ?? familyHead}</Typography>
      </Breadcrumbs>

      {isLoading || !data ? (
        <Skeleton variant="rectangular" height={400} />
      ) : (
        <>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h5" component="h1">
              {data.name}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              family-head <code>{data.familyHead}</code> · {data.bases.length} bases
            </Typography>
          </Paper>

          <Stack spacing={1}>
            {data.bases.map((base) => (
              <Accordion key={base.id} disableGutters>
                <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                  <Stack direction="row" spacing={2} alignItems="center" sx={{ width: '100%' }}>
                    {base.afasItemcode && (
                      <Typography
                        component="code"
                        sx={{ fontFamily: 'monospace', color: 'text.secondary', minWidth: '6ch' }}
                      >
                        {base.afasItemcode}
                      </Typography>
                    )}
                    <Typography sx={{ flexGrow: 1 }}>{base.name}</Typography>
                    <Chip label={base.languageCode} size="small" />
                    <Typography variant="caption" color="text.secondary">
                      {base.items.length} items
                    </Typography>
                  </Stack>
                </AccordionSummary>
                <AccordionDetails sx={{ p: 0 }}>
                  <TableContainer>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>Itemcode</TableCell>
                          <TableCell>Label</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {base.items.map((item) => (
                          <TableRow key={item.itemcode}>
                            <TableCell>
                              <code>{item.itemcode}</code>
                            </TableCell>
                            <TableCell>{item.label}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                </AccordionDetails>
              </Accordion>
            ))}
          </Stack>
        </>
      )}
    </Stack>
  );
}
