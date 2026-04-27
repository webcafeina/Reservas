import { WebcafeinaFooter } from '../src/components/WebcafeinaFooter';

import { Dashboard } from './pages/Dashboard';
import { Health } from './pages/Health';
import { Calendar } from './pages/Calendar';
import { BookingsList } from './pages/BookingsList';
import { BookingNew } from './pages/BookingNew';
import { BookingDetail } from './pages/BookingDetail';
import { SettingsPage } from './pages/Settings';
import { useHashRoute, type AdminView } from './useHashRoute';

import styles from './AdminApp.module.css';

const NAV = [
    { path: 'dashboard', label: 'Panel' },
    { path: 'calendar', label: 'Calendario' },
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

    return (
        <div className={styles.wrapper}>
            <header className={styles.header}>
                <h1>Reservas Aldealab</h1>
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
            </header>

            <main className={styles.main}>
                {view.name === 'dashboard' && <Dashboard />}
                {view.name === 'health' && <Health />}
                {view.name === 'calendar' && <Calendar />}
                {view.name === 'bookings' && <BookingsList />}
                {view.name === 'bookings-new' && <BookingNew />}
                {view.name === 'booking' && <BookingDetail id={view.id} />}
                {view.name === 'settings' && <SettingsPage />}
            </main>

            <WebcafeinaFooter />
        </div>
    );
}
