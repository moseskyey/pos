import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/inter/800.css';
import '../scss/app.scss';

import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';
import TomSelect from 'tom-select';

import './ui';
import './charts';
import './pos';

window.bootstrap = bootstrap;
window.Chart = Chart;
window.TomSelect = TomSelect;
