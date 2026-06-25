import { useState, MouseEvent } from 'react';
import {
  AppBar,
  Box,
  Button,
  Container,
  Menu,
  MenuItem,
  Stack,
  Toolbar,
  Typography,
} from '@mui/material';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import { Outlet, Link as RouterLink, useLocation, useNavigate } from 'react-router-dom';

interface NavLink {
  to: string;
  label: string;
}

interface NavGroup {
  label: string;
  links: NavLink[];
}

const groups: NavGroup[] = [
  {
    label: 'Catalogus',
    links: [
      { to: '/', label: 'Groepen' },
      { to: '/accessoires', label: 'Accessoires' },
    ],
  },
  {
    label: 'Audits',
    links: [
      { to: '/missing', label: 'Missing variants' },
      { to: '/no-match', label: 'No-match varianten' },
      { to: '/online-not-assigned', label: 'Online niet toegekend' },
      { to: '/name-drift', label: 'Name drift' },
      { to: '/suspicious-bases', label: 'Suspicious bases' },
      { to: '/duplicate-boms', label: 'Duplicate BOMs' },
      { to: '/sticker-drift', label: 'Sticker drift' },
      { to: '/product-type-issues', label: 'Producttype-issues' },
    ],
  },
  {
    label: 'Prijzen',
    links: [
      { to: '/price-drift', label: 'Price drift' },
      { to: '/base-price-gaps', label: 'Base-prijs-gaten' },
      { to: '/prijslijst-whitelist', label: 'Prijslijst-whitelist' },
    ],
  },
  {
    label: 'Config',
    links: [{ to: '/blacklist', label: 'BOM-blacklist' }],
  },
  {
    label: 'Settings',
    links: [
      { to: '/settings/websites', label: 'Websites' },
      { to: '/woocommerce', label: 'WooCommerce' },
    ],
  },
];

export function App() {
  const location = useLocation();
  const navigate = useNavigate();
  const [openMenu, setOpenMenu] = useState<string | null>(null);
  const [anchor, setAnchor] = useState<HTMLElement | null>(null);

  const isActive = (to: string) =>
    to === '/' ? location.pathname === '/' : location.pathname.startsWith(to);

  const isGroupActive = (group: NavGroup) => group.links.some((l) => isActive(l.to));

  const handleOpen = (label: string) => (event: MouseEvent<HTMLButtonElement>) => {
    setOpenMenu(label);
    setAnchor(event.currentTarget);
  };
  const handleClose = () => {
    setOpenMenu(null);
    setAnchor(null);
  };
  const handleSelect = (to: string) => {
    handleClose();
    navigate(to);
  };

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
            {groups.map((group) => {
              const active = isGroupActive(group);
              return (
                <Button
                  key={group.label}
                  onClick={handleOpen(group.label)}
                  color={active ? 'primary' : 'inherit'}
                  variant={active ? 'outlined' : 'text'}
                  size="small"
                  endIcon={<KeyboardArrowDownIcon />}
                >
                  {group.label}
                </Button>
              );
            })}
          </Stack>
          {groups.map((group) => (
            <Menu
              key={group.label}
              anchorEl={anchor}
              open={openMenu === group.label}
              onClose={handleClose}
              anchorOrigin={{ vertical: 'bottom', horizontal: 'left' }}
              transformOrigin={{ vertical: 'top', horizontal: 'left' }}
            >
              {group.links.map((link) => (
                <MenuItem
                  key={link.to}
                  selected={isActive(link.to)}
                  onClick={() => handleSelect(link.to)}
                >
                  {link.label}
                </MenuItem>
              ))}
            </Menu>
          ))}
        </Toolbar>
      </AppBar>
      <Container component="main" maxWidth={false} sx={{ py: 4, flexGrow: 1 }}>
        <Outlet />
      </Container>
    </Box>
  );
}
