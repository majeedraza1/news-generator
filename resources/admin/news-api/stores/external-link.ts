import http from "../../../utils/axios";
import crudOperation from "../../../utils/CrudOperation";
const getExternalLinks = (page: number = 1) => {
  http
    .get('external-link', {params: {page: page}})
    .then(response => {

    })
}

export {
  getExternalLinks
}