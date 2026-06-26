/**
 * src/index.js
 * Settings SPA entry point — mounts React app into #pgbf-settings-root.
 * Mirrors the COS Pro index.js pattern exactly.
 */
import { createRoot } from 'react-dom/client';
import { HashRouter } from 'react-router-dom';
import App from './App';
import { SettingsProvider } from './context/SettingsContext';
import './app.scss';

window.addEventListener(
    'load',
    function () {
        const container = document.querySelector( '#pgbf-settings-root' );
        if ( ! container ) {
            console.error( '[PGBF Pro] #pgbf-settings-root not found.' );
            return;
        }
        const root = createRoot( container );
        root.render(
            <SettingsProvider>
                <HashRouter>
                    <App />
                </HashRouter>
            </SettingsProvider>
        );
    },
    false
);
