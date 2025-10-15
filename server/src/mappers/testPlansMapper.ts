import { RuntimeMetadataDTO, TestPlanDTO } from '../types/dto.js';
import { mapRuntimeMetadata } from './runsMapper.js';

interface RawGroupRecord {
  group_id: string;
  status: string;
  started_at?: string;
  finished_at?: string | null;
  targets?: string;
  threads?: number;
  duration?: number;
  engine?: string;
  behavior_profile_id?: string;
  stealth_profile?: string;
  attack_method?: string;
  proxy_profile?: string;
  runtime?: Record<string, any>;
  [key: string]: any;
}

export const mapTestPlan = (record: RawGroupRecord): TestPlanDTO => {
  const runtime: RuntimeMetadataDTO | undefined = mapRuntimeMetadata(record.runtime);

  return {
    id: record.group_id,
    status: record.status ?? 'UNKNOWN',
    started_at: record.started_at,
    finished_at: record.finished_at,
    targets: Array.isArray(record.targets)
      ? record.targets
      : typeof record.targets === 'string'
        ? record.targets
            .split(',')
            .map((target) => target.trim())
            .filter(Boolean)
        : undefined,
    threads: record.threads != null ? Number(record.threads) : undefined,
    duration: record.duration != null ? Number(record.duration) : undefined,
    engine: record.engine,
    behavior_profile_id: record.behavior_profile_id,
    runtime,
  };
};
