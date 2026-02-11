import { useState, useEffect, useCallback, useRef } from 'react';
import { getNoturApi } from '@notur/sdk';

const EXTENSION_ID = 'notur/cs2-modframework';

export interface FrameworkStatus {
    installed: boolean;
    directory: string | null;
}

export interface StatusResponse {
    swiftly: FrameworkStatus;
    counterstrikesharp: FrameworkStatus;
    metamod: FrameworkStatus;
    gameinfo_entries: Record<string, boolean>;
}

export interface VersionInfo {
    version: string;
    download_url: string;
    filename: string;
}

export interface VersionsResponse {
    swiftly: VersionInfo | null;
    counterstrikesharp: VersionInfo | null;
    metamod: VersionInfo | null;
}

export interface InstallResult {
    success: boolean;
    framework: string;
    version?: string;
    message: string;
}

export type FrameworkName = 'swiftly' | 'counterstrikesharp' | 'metamod';

export function useModFramework(serverUuid: string) {
    const notur = getNoturApi();

    const api = notur.hooks.useExtensionApi({
        extensionId: EXTENSION_ID,
        baseUrl: `/api/client/notur/${EXTENSION_ID}/servers/${serverUuid}`,
    });

    const [status, setStatus] = useState<StatusResponse | null>(null);
    const [versions, setVersions] = useState<VersionsResponse | null>(null);
    const [statusLoading, setStatusLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState<FrameworkName | null>(null);
    const [error, setError] = useState<string | null>(null);

    // Use refs for the API methods to avoid dependency churn in useEffect
    const apiGetRef = useRef(api.get);
    const apiPostRef = useRef(api.post);
    apiGetRef.current = api.get;
    apiPostRef.current = api.post;

    const fetchStatus = useCallback(async () => {
        setStatusLoading(true);
        setError(null);
        try {
            const res = await apiGetRef.current('/status') as { data: StatusResponse };
            setStatus(res.data);
        } catch (e: any) {
            setError(e.message || 'Failed to fetch status');
        } finally {
            setStatusLoading(false);
        }
    }, []);

    const fetchVersions = useCallback(async () => {
        try {
            const res = await apiGetRef.current('/versions') as { data: VersionsResponse };
            setVersions(res.data);
        } catch {
            // Non-critical — versions are supplementary info
        }
    }, []);

    const installFramework = useCallback(async (framework: FrameworkName): Promise<InstallResult> => {
        setActionLoading(framework);
        setError(null);
        try {
            const res = await apiPostRef.current('/install', { framework }) as { data: InstallResult };
            // Refresh status separately — a refresh failure must not mask a successful install
            fetchStatus().catch(() => {});
            return res.data;
        } catch (e: any) {
            const message = e.message || `Failed to install ${framework}`;
            setError(message);
            return { success: false, framework, message };
        } finally {
            setActionLoading(null);
        }
    }, [fetchStatus]);

    const uninstallFramework = useCallback(async (framework: FrameworkName): Promise<InstallResult> => {
        setActionLoading(framework);
        setError(null);
        try {
            const res = await apiPostRef.current('/uninstall', { framework }) as { data: InstallResult };
            // Refresh status separately — a refresh failure must not mask a successful uninstall
            fetchStatus().catch(() => {});
            return res.data;
        } catch (e: any) {
            const message = e.message || `Failed to uninstall ${framework}`;
            setError(message);
            return { success: false, framework, message };
        } finally {
            setActionLoading(null);
        }
    }, [fetchStatus]);

    useEffect(() => {
        if (serverUuid) {
            fetchStatus();
            fetchVersions();
        }
    }, [serverUuid, fetchStatus, fetchVersions]);

    return {
        status,
        versions,
        statusLoading,
        actionLoading,
        error,
        fetchStatus,
        installFramework,
        uninstallFramework,
    };
}
