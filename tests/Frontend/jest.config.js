module.exports = {
    preset: 'ts-jest',
    testEnvironment: 'jsdom',
    roots: ['<rootDir>'],
    moduleNameMapper: {
        '^@bridge/(.*)$': '<rootDir>/../../bridge/src/$1',
        '^react$': '<rootDir>/../../node_modules/react',
        '^react-dom$': '<rootDir>/../../node_modules/react-dom',
    },
    transform: {
        '^.+\\.tsx?$': ['ts-jest', {
            tsconfig: {
                jsx: 'react',
                esModuleInterop: true,
            },
        }],
    },
};
