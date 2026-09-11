import axios from 'axios'

const isDecryptFailed = result => JSON.stringify(result?.result ?? result ?? {})
    .toLowerCase()
    .includes('decrypt_failed')

export const printHdmReceipt = async (printData, gateway, locale) => {
    const gatewayUrl = `${gateway.url}?token=${gateway.token}`
    const updateUrl = route('hdm.update_operation_status', { locale })
    const updateOperation = async result => {
        const responsePayload = result.result ?? result
        const updateData = {
            operation_id: printData.operation_id,
            status: result.success ? 'success' : 'failed',
            response: responsePayload,
            crn: responsePayload?.crn ?? result.crn ?? null,
            rseq: responsePayload?.rseq ?? result.rseq ?? null,
        }

        if (result.new_session_key && result.cashier_id) {
            updateData.cashier_id = result.cashier_id
            updateData.new_session_key = result.new_session_key
        }

        await axios.post(updateUrl, updateData)
    }

    try {
        const sendPrint = async (forceLogin = false) => {
            const response = await axios.post(gatewayUrl, {
                op: printData.gateway_operation ?? 'print',
                device_id: printData.device.id,
                device_ip: printData.device.ip,
                device_port: printData.device.port,
                device_password: printData.device.password,
                session_key: forceLogin ? null : (printData.cashier.session_key ?? null),
                cashier_login: printData.cashier.login,
                cashier_pin: printData.cashier.pin,
                cashier_id: printData.cashier.id,
                force_login: forceLogin,
                payload: printData.receipt,
            })

            return response.data
        }

        let result = await sendPrint(false)

        if (!result.success && isDecryptFailed(result)) {
            result = await sendPrint(true)
        }

        await updateOperation(result)

        return result.success
            ? { success: true, result }
            : { success: false, message: result.message ?? 'HDM printing failed.', result }
    } catch (error) {
        const message = error.response?.data?.message ?? error.message ?? 'HDM gateway connection failed.'

        try {
            await updateOperation({
                success: false,
                result: {
                    error: 'GATEWAY_CONNECTION_FAILED',
                    message,
                },
            })
        } catch {
            // Preserve the original gateway error when status synchronization also fails.
        }

        return {
            success: false,
            message,
        }
    }
}
