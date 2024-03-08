import {AxiosInstance} from 'axios';
import {Notify, Spinner} from '@shapla/vue-components';

interface PaginationDataInterface {
  total_items: number;
  per_page: number;
  current_page: number;
  total_pages: number;
}

interface StatusDataInterface {
  active: boolean;
  count: number;
  key: string;
  label: string;
}

interface ScreenOptionDataInterface {
  excluded_columns: string[];
  per_page: number;
  columns: { key: string; label: string; }[];
  actions: { key: string; label: string; }[];
}

interface CollectionArgumentInterface {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  sort?: string | { field: string; order: 'ASC' | 'DESC' }[];

  [key: string]: any;
}

interface ServerCollectionResponseDataInterface {
  items: unknown[];
  pagination: PaginationDataInterface;
  statuses?: StatusDataInterface[];
  screen_options?: ScreenOptionDataInterface;

  [key: string]: unknown;
}

interface ServerSuccessResponseInterface {
  success: boolean;
  data: Record<string, any>;
}

interface ServerErrorResponseInterface {
  success: boolean;
  code: string;
  message: string;
  errors?: Record<string, string>;
}

class CrudOperation {
  private endpoint: string;
  private http: AxiosInstance;

  constructor(endpoint: string, http: AxiosInstance) {
    this.endpoint = endpoint;
    this.http = http;
  }

  /**
   * Get collection of items
   *
   * @param  params
   */
  public getItems(params: CollectionArgumentInterface = {}): Promise<ServerCollectionResponseDataInterface> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .get(this.endpoint, {params})
        .then((response) => {
          const data =
            response.data as ServerSuccessResponseInterface;
          resolve(data.data as ServerCollectionResponseDataInterface);
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }

  /**
   * Get single item
   *
   * @param  id
   */
  public getItem(id: number): Promise<Record<string, unknown>> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .get(`${this.endpoint}/${id}`)
        .then((response) => {
          const data =
            response.data as ServerSuccessResponseInterface;
          resolve(data.data);
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }

  /**
   * Create a new record in the collection
   *
   * @param  data
   */
  public createItem(data: Record<string, any>): Promise<unknown> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .post(this.endpoint, data)
        .then((response) => {
          const responseData =
            response.data as ServerSuccessResponseInterface;
          resolve(responseData.data);
          Notify.success('Item created successfully', 'Success!');
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }

  /**
   * Update an existing item from the collection
   *
   * @param  id
   * @param  data
   */
  public updateItem(id: number, data: Record<string, any>): Promise<Record<string, unknown>> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .put(`${this.endpoint}/${id}`, data)
        .then((response) => {
          const responseData =
            response.data as ServerSuccessResponseInterface;
          resolve(responseData.data);
          Notify.success('Item updated successfully', 'Success!');
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }

  /**
   * Delete an item from the collection
   *
   * @param  id
   */
  public deleteItem(id: number): Promise<any> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .delete(`${this.endpoint}/${id}`)
        .then((response) => {
          const responseData =
            response.data as ServerSuccessResponseInterface;
          resolve(responseData.data);
          Notify.success('Item deleted successfully', 'Success!');
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }

  /**
   * Trash an item from the collection
   *
   * @param  id
   */
  public trashItem(id: number): Promise<any> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .post(`${this.endpoint}/${id}/trash`)
        .then((response) => {
          const responseData =
            response.data as ServerSuccessResponseInterface;
          resolve(responseData.data);
          Notify.success('Item trashed successfully', 'Success!');
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }

  /**
   * Restore an item from the collection
   *
   * @param  id
   */
  public restoreItem(id: number): Promise<any> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .post(`${this.endpoint}/${id}/restore`)
        .then((response) => {
          const responseData =
            response.data as ServerSuccessResponseInterface;
          resolve(responseData.data);
          Notify.success('Item restored successfully', 'Success!');
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }

  public batch(action, payload = []): Promise<any> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .post(`${this.endpoint}/batch`, {action, payload})
        .then((response) => {
          const responseData =
            response.data as ServerSuccessResponseInterface;
          resolve(responseData.data);
          Notify.success('The batch action run successfully.', 'Success!');
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }

  public post(endpoint, payload = []): Promise<any> {
    return new Promise((resolve) => {
      Spinner.activate();
      this.http
        .post(endpoint, payload)
        .then((response) => {
          const responseData =
            response.data as ServerSuccessResponseInterface;
          resolve(responseData.data);
        })
        .catch((error) => {
          const responseData = error.response
            .data as ServerErrorResponseInterface;
          if (responseData.message) {
            Notify.error(responseData.message, 'Error!');
          }
        })
        .finally(() => {
          Spinner.deactivate();
        });
    });
  }
}

export type {
  PaginationDataInterface,
  StatusDataInterface,
  ServerCollectionResponseDataInterface,
  ServerSuccessResponseInterface,
  ScreenOptionDataInterface
};
export default CrudOperation;
