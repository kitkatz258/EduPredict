import './bootstrap';
import 'remixicon/fonts/remixicon.css';
import 'sweetalert2/dist/sweetalert2.min.css';
import Chart from 'chart.js/auto';
import { installFeedback } from './feedback';

window.Chart = Chart;

installFeedback();
