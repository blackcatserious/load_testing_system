import type { Config } from 'jest';

const config: Config = {
  rootDir: '.',
  preset: 'ts-jest/presets/default-esm',
  testEnvironment: 'node',
  moduleNameMapper: {
    '^(\.\.?/.*)\.js$': '$1',
  },
  extensionsToTreatAsEsm: ['.ts'],
  globals: {
    'ts-jest': {
      useESM: true,
      tsconfig: '<rootDir>/tsconfig.json',
    },
  },
  testMatch: ['**/tests/**/*.test.ts'],
};

export default config;
