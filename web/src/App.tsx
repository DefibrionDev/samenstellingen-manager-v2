import { AppBar, Box, Button, Container, Stack, Toolbar, Typography } from '@mui/material';
import { Outlet, Link as RouterLink, useLocation } from 'react-router-dom';

const navLinks = [
  { to: '/', label: 'Groepen' },
  { to: '/accessoires', label: 'Accessoires' },
  { to: '/blacklist', label: 'Blacklist' },
  { to: '/missing', label: 'Missing' },
  { to: '/name-drift', label: 'Name drift' },
];

export function App() {
  const location = useLocation();
  const isActive = (to: string) =>
    to === '/' ? location.pathname === '/' : location.pathname.startsWith(to);

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
      <AppBar position="static" color="default" elevation={1}>
        <Toolbar>
          <Typography
            variant="h6"
            component={RouterLink}
            to="/"
            sx={{ color: 'inherit', textDecoration: 'none', mr: 4 }}
          >
            Samenstellingen Manager
          </Typography>
          <Stack direction="row" spacing={1} component="nav" sx={{ flexGrow: 1 }}>
            {navLinks.map((link) => (
              <Button
                key={link.to}
                component={RouterLink}
                to={link.to}
                color={isActive(link.to) ? 'primary' : 'inherit'}
                variant={isActive(link.to) ? 'outlined' : 'text'}
                size="small"
              >
                {link.label}
              </Button>
            ))}
          </Stack>
        </Toolbar>
      </AppBar>
      <Container component="main" maxWidth={false} sx={{ py: 4, flexGrow: 1 }}>
        <Outlet />
      </Container>
    </Box>
  );
}
