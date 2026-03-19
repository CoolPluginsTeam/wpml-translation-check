import SetupPage from './pages/setup-page.jsx';
import './wizard.css';
import { createRoot } from 'react-dom/client';

function mount() {
	const el = document.getElementById( 'automlp-ai-setup' );
	if ( el ) {
		const root = createRoot( el );
		root.render( <SetupPage /> );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}