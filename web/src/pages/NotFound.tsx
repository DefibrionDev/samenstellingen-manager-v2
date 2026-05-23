import { Alert, Button, Stack } from '@mui/material';
import { Link as RouterLink } from 'react-router-dom';

export function NotFound() {
  return (
    <Stack spacing={2}>
      <Alert severity="warning">Pagina niet gevonden.</Alert>
      <Button component={RouterLink} to="/" variant="outlined" sx={{ alignSelf: 'flex-start' }}>
        Terug naar groepen
      </Button>
    </Stack>
  );
}
