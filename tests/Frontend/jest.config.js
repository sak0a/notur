module.exports = {
    preset: 'ts-jest',
    testEnvironment: 'jsdom',
    roots: ['<rootDir>'],
    moduleNameMapper: {
        '^@bridge/(.*)$': '<rootDir>/../../bridge/src/$1',
    },
    transform: {
        '^.+\\.tsx?$': 'ts-jest',
    },
};
