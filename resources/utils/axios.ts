import axios from 'axios';

const axiosArgs = {
    baseURL: window.TeraPixelNewsGenerator.restRoot,
    headers: {},
};
if (window.TeraPixelNewsGenerator && window.TeraPixelNewsGenerator.restNonce) {
    axiosArgs.headers = {'X-WP-Nonce': window.TeraPixelNewsGenerator.restNonce};
}

const http = axios.create(axiosArgs);

export default http;
