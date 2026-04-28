import { WebcafeinaFooter } from '../src/components/WebcafeinaFooter';

import { Dashboard } from './pages/Dashboard';
import { Health } from './pages/Health';
import { BookingsList } from './pages/BookingsList';
import { BookingNew } from './pages/BookingNew';
import { BookingDetail } from './pages/BookingDetail';
import { SettingsPage } from './pages/Settings';
import { useHashRoute, type AdminView } from './useHashRoute';

import styles from './AdminApp.module.css';

const NAV = [
    { path: 'dashboard', label: 'Panel' },
    { path: 'bookings', label: 'Reservas' },
    { path: 'settings', label: 'Ajustes' },
    { path: 'health', label: 'Estado' },
];

function isActive(view: AdminView, path: string): boolean {
    if (path === 'bookings') {
        return view.name === 'bookings' || view.name === 'booking';
    }
    return view.name === path;
}

export function AdminApp(): JSX.Element {
    const view = useHashRoute();
    const logoUrl = window.ReservasAldealabAdmin?.logoUrl ?? null;

    return (
        <div className={styles.wrapper}>
            <header className={styles.header}>
                <div className={styles.headerLeft}>
                    <h1>Gestor de reservas de AldeaLab</h1>
                    <nav className={styles.nav}>
                        {NAV.map((item) => (
                            <a
                                key={item.path}
                                href={`#/${item.path}`}
                                className={`${styles.navLink} ${isActive(view, item.path) ? styles.navActive : ''}`}
                            >
                                {item.label}
                            </a>
                        ))}
                    </nav>
                </div>
                {logoUrl !== null && logoUrl !== '' && (
                    <img
                        src={logoUrl}
                        alt=""
                        aria-hidden="true"
                        className={styles.headerLogo}
                        onError={(e) => {
                            // If the file is missing or fails to load,
                            // hide it instead of leaving a broken image.
                            e.currentTarget.style.display = 'none';
                        }}
                    />
                )}
            </header>

            <main className={styles.main}>
                {view.name === 'dashboard' && <Dashboard />}
                {view.name === 'health' && <Health />}
                {view.name === 'bookings' && <BookingsList />}
                {view.name === 'bookings-new' && <BookingNew />}
                {view.name === 'booking' && <BookingDetail id={view.id} />}
                {view.name === 'settings' && <SettingsPage />}
            </main>

            <WebcafeinaFooter />
        </div>
    );
}
