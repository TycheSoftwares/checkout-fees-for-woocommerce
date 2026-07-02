/**
 * src/settings/App.js
 *
 * Root component — Card shell + NavLink tabs + Routes.
 * Tab order: Dashboard | General | Payment Gateways | Info |
 *            Global Extra Fee | BIN APIs
 */

import {
    Card,
    CardHeader,
    CardBody,
    CardFooter,
    Spinner,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalHeading as Heading,
    __experimentalText as Text,
    ExternalLink,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Navigate, Route, Routes, NavLink } from 'react-router-dom';
import { useSettings } from './context/SettingsContext';

import Dashboard      from './screens/Dashboard';
import General        from './screens/General';
import GatewaySettings from './screens/GatewaySettings';
import GlobalExtraFee from './screens/GlobalExtraFee';
import Info           from './screens/Info';
import BinApis        from './screens/BinApis';

const SVG_PROPS = {
    xmlns         : 'http://www.w3.org/2000/svg',
    width         : 18,
    height        : 18,
    viewBox       : '0 0 24 24',
    fill          : 'none',
    stroke        : 'currentColor',
    strokeWidth   : 2,
    strokeLinecap : 'round',
    strokeLinejoin: 'round',
    style         : { flexShrink: 0 },
};

const IconDashboard = () => (
    <svg { ...SVG_PROPS }>
        <rect width="7" height="9"  x="3"  y="3"  rx="1" />
        <rect width="7" height="5"  x="14" y="3"  rx="1" />
        <rect width="7" height="9"  x="14" y="12" rx="1" />
        <rect width="7" height="5"  x="3"  y="16" rx="1" />
    </svg>
);

const IconSettings = () => (
    <svg { ...SVG_PROPS }>
        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
        <circle cx="12" cy="12" r="3" />
    </svg>
);

const IconCreditCard = () => (
    <svg { ...SVG_PROPS }>
        <rect width="20" height="14" x="2" y="5" rx="2" />
        <line x1="2" x2="22" y1="10" y2="10" />
    </svg>
);

const IconCog = () => (
    <svg { ...SVG_PROPS }>
        <path d="M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" />
        <path d="M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
        <path d="M12 2v2" />
        <path d="M12 22v-2" />
        <path d="m17 20.66-1-1.73" />
        <path d="M11 10.27 7 3.34" />
        <path d="m20.66 17-1.73-1" />
        <path d="m3.34 7 1.73 1" />
        <path d="M14 12h8" />
        <path d="M2 12h2" />
        <path d="m20.66 7-1.73 1" />
        <path d="m3.34 17 1.73-1" />
        <path d="m17 3.34-1 1.73" />
        <path d="m11 13.73-4 6.93" />
    </svg>
);

const IconInfo = () => (
    <svg { ...SVG_PROPS }>
        <circle cx="12" cy="12" r="10" />
        <path d="M12 16v-4" />
        <path d="M12 8h.01" />
    </svg>
);

const IconDollar = () => (
    <svg { ...SVG_PROPS }>
        <line x1="12" x2="12" y1="2" y2="22" />
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
    </svg>
);

const IconKey = () => (
    <svg { ...SVG_PROPS }>
        <path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4" />
        <path d="m21 2-9.6 9.6" />
        <circle cx="7.5" cy="15.5" r="5.5" />
    </svg>
);

const TABS = [
    { name: 'dashboard',        title: __( 'Dashboard',        'checkout-fees-for-woocommerce' ), path: '/',                Icon: IconDashboard },
    { name: 'general',          title: __( 'General',          'checkout-fees-for-woocommerce' ), path: '/general',         Icon: IconSettings  },
    { name: 'gateways',         title: __( 'Payment Gateways', 'checkout-fees-for-woocommerce' ), path: '/gateways',        Icon: IconCreditCard },
    { name: 'info',             title: __( 'Info',             'checkout-fees-for-woocommerce' ), path: '/info',            Icon: IconInfo      },
    { name: 'global-extra-fee', title: __( 'Global Extra Fee', 'checkout-fees-for-woocommerce' ), path: '/global-extra-fee', Icon: IconDollar    },
    { name: 'bin-apis',         title: __( 'BIN APIs',         'checkout-fees-for-woocommerce' ), path: '/bin-apis',        Icon: IconCog       },
];

function App() {
    const { isLoading, loadedSections } = useSettings();

    // Block rendering routes until the initial settings fetch completes.
    // This prevents the 1-2 second flash where fields show default/empty
    // values before the API response arrives.
    const isInitialising = isLoading && ! loadedSections.settings;

    return (
        <Card>
            <CardHeader>
                <VStack spacing={ 2 }>
                    <Heading level={ 4 }>
                        { __( 'Payment Gateway Based Fees and Discounts', 'checkout-fees-for-woocommerce' ) }
                    </Heading>
                    <Text>
                        { __( 'Configure fees and discounts for WooCommerce payment gateways', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                </VStack>
            </CardHeader>

            <CardBody style={ { paddingTop: '0px' } }>
                <VStack>
                    { /* ── Tab navigation ── */ }
                    <HStack style={ { borderBottom: '1px solid #e5e5e5', flexWrap: 'wrap' } }>
                        <div className="pgbf-header-dashboard-tabs">
                            { TABS.map( ( { name, title, path, Icon } ) => (
                                <NavLink
                                    key={ name }
                                    to={ path }
                                    className={ ( { isActive } ) =>
                                        'pgbf-dashboard-tab' + ( isActive ? ' is-active' : '' )
                                    }
                                    end={ path === '/' }
                                >
                                    <Icon />
                                    <span>{ title }</span>
                                </NavLink>
                            ) ) }
                        </div>
                    </HStack>

                    { /* ── Screen routing — show spinner until initial load ── */ }
                    { isInitialising ? (
                        <div style={ { padding: '60px', textAlign: 'center' } }>
                            <Spinner style={ { width: 28, height: 28 } } />
                        </div>
                    ) : (
                        <Routes>
                            <Route path="/"                 element={ <Dashboard /> } />
                            <Route path="/general"          element={ <General /> } />
                            <Route path="/gateways"         element={ <GatewaySettings /> } />
                            <Route path="/info"             element={ <Info /> } />
                            <Route path="/global-extra-fee" element={ <GlobalExtraFee /> } />
                            <Route path="/bin-apis"         element={ <BinApis /> } />
                            <Route path="*"                 element={ <Navigate to="/" replace /> } />
                        </Routes>
                    ) }
                </VStack>
            </CardBody>

            <CardFooter justify="center">
                <VStack style={ { padding: '20px 0' } }>
                    <HStack justify="center" style={ { marginBottom: '22px' } }>
                        <ExternalLink href="https://support.tychesoftwares.com/help/2285384554/">
                            { __( 'Need support?', 'checkout-fees-for-woocommerce' ) }
                        </ExternalLink>
                        <Text style={ { fontWeight: 'bold' } }>
                            { __( "We're always happy to help you.", 'checkout-fees-for-woocommerce' ) }
                        </Text>
                    </HStack>
                    <HStack justify="center">
                        <Text>{ __( 'If this plugin helped you,', 'checkout-fees-for-woocommerce' ) }</Text>
                        <ExternalLink href="https://wordpress.org/support/plugin/checkout-fees-for-woocommerce/reviews/#new-post" className="pgbf-link">
                            { __( 'please rate it', 'checkout-fees-for-woocommerce' ) }
                        </ExternalLink>
                        <Text style={ { fontSize: '17px', color: '#FFBA00' } }>★★★★★</Text>
                    </HStack>
                </VStack>
            </CardFooter>
        </Card>
    );
}

export default App;