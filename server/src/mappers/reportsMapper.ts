import { ReportDTO } from '../types/dto.js';

interface RawReportRecord {
  filename: string;
  file_path?: string;
  size?: number;
  created_at?: string;
  type?: string;
  run_id?: string;
  summary?: any;
  [key: string]: any;
}

export const mapReport = (record: RawReportRecord): ReportDTO => ({
  filename: record.filename,
  file_path: record.file_path,
  size: record.size != null ? Number(record.size) : undefined,
  created_at: record.created_at,
  type: record.type,
  run_id: record.run_id,
  summary: record.summary,
});
