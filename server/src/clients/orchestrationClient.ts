import axios, { AxiosInstance } from 'axios';
import { ORCHESTRATOR_BASE_URL, ORCHESTRATOR_TIMEOUT } from '../config.js';

export interface OrchestrationResponse<T = any> {
  status?: 'success' | 'error';
  success?: boolean;
  message?: string;
  error?: string;
  data?: T;
  [key: string]: any;
}

class OrchestrationClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: ORCHESTRATOR_BASE_URL,
      timeout: ORCHESTRATOR_TIMEOUT,
    });
  }

  async get<T>(path: string, params?: Record<string, any>): Promise<OrchestrationResponse<T>> {
    const response = await this.client.get<OrchestrationResponse<T>>(path, { params });
    return response.data;
  }

  async post<T>(path: string, body?: any): Promise<OrchestrationResponse<T>> {
    const response = await this.client.post<OrchestrationResponse<T>>(path, body);
    return response.data;
  }
}

export const orchestrationClient = new OrchestrationClient();
