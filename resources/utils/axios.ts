import axios from 'axios';

const axiosArgs = {
    baseURL: window.StackonetNewsGenerator.restRoot,
    headers: {},
};
if (window.StackonetNewsGenerator && window.StackonetNewsGenerator.restNonce) {
    axiosArgs.headers = {'X-WP-Nonce': window.StackonetNewsGenerator.restNonce};
}

const http = axios.create(axiosArgs);

export default http;
